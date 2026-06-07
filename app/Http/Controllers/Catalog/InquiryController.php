<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CheckoutRequest;
use App\Http\Requests\Catalog\StoreInquiryRequest;
use App\Http\Requests\Catalog\ValidateCouponRequest;
use App\Models\Coupon;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use App\Models\Setting;
use App\Notifications\NewInquiryNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

class InquiryController extends Controller
{
    /**
     * Recibe una consulta desde el detalle del producto (un solo item).
     */
    public function store(StoreInquiryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $inquiry = DB::transaction(function () use ($data) {
            $inquiry = Inquiry::create([
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'message' => $data['message'] ?? null,
                'source' => $data['source'] ?? 'web',
                'status' => 'pendiente',
            ]);

            if (! empty($data['product_id'])) {
                $product = Product::find($data['product_id']);
                if ($product) {
                    $qty = $data['quantity'] ?? 1;
                    InquiryItem::create([
                        'inquiry_id' => $inquiry->id,
                        'product_id' => $product->id,
                        'product_name_snapshot' => $product->name,
                        'product_code_snapshot' => $product->code,
                        'quantity' => $qty,
                        'unit_price' => $product->sale_price ?: $product->price,
                    ]);
                    $inquiry->update(['total_estimated' => ($product->sale_price ?: $product->price) * $qty]);
                }
            }

            return $inquiry;
        });

        $this->notifyAdmin($inquiry);

        return back()->with('success', '¡Recibimos tu consulta! Te contactaremos pronto.');
    }

    /**
     * Recibe el carrito completo desde /carrito (checkout).
     */
    public function checkout(CheckoutRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $inquiry = DB::transaction(function () use ($data) {
            $inquiry = Inquiry::create([
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'message' => $data['message'] ?? null,
                'source' => $data['source'] ?? 'web',
                'status' => 'pendiente',
            ]);

            $total = 0;
            $productIds = collect($data['items'])->pluck('product_id')->unique();
            $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($data['items'] as $row) {
                $p = $products->get($row['product_id']);
                if (! $p) {
                    continue;
                }
                $unit = (float) ($p->sale_price ?: $p->price);
                $qty = (int) $row['quantity'];
                $total += $unit * $qty;

                InquiryItem::create([
                    'inquiry_id' => $inquiry->id,
                    'product_id' => $p->id,
                    'product_name_snapshot' => $p->name,
                    'product_code_snapshot' => $p->code,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                ]);
            }

            // Aplicar cupón si corresponde
            $discount = 0.0;
            $couponCode = null;
            if (! empty($data['coupon_code'])) {
                $code = strtoupper(trim($data['coupon_code']));
                $coupon = Coupon::where('codigo', $code)->lockForUpdate()->first();
                if ($coupon && $coupon->isUsable($total)) {
                    $discount = $coupon->discountFor($total);
                    $couponCode = $coupon->code;
                    $coupon->increment('usos_realizados');
                }
            }

            $inquiry->update([
                'total_estimated' => max(0, $total - $discount),
                'coupon_code' => $couponCode,
                'discount_amount' => $discount > 0 ? $discount : null,
            ]);

            return $inquiry;
        });

        $this->notifyAdmin($inquiry);

        return redirect(URL::signedRoute('cart.thanks', ['inquiry' => $inquiry->public_token]))
            ->with('success', '¡Recibimos tu pedido! Te contactaremos pronto.');
    }

    /**
     * Valida un código de cupón vs subtotal. Devuelve JSON para el frontend del carrito.
     */
    public function validateCoupon(ValidateCouponRequest $request): JsonResponse
    {
        $data = $request->validated();

        $coupon = Coupon::where('codigo', $data['code'])->first();

        if (! $coupon || ! $coupon->isUsable((float) $data['subtotal'])) {
            return response()->json([
                'valid' => false,
                'message' => 'Cupón inválido, vencido o subtotal insuficiente.',
            ]);
        }

        return response()->json([
            'valid' => true,
            'code' => $coupon->code,
            'description' => $coupon->description,
            'type' => $coupon->type,
            'value' => (float) $coupon->value,
            'discount' => $coupon->discountFor((float) $data['subtotal']),
        ]);
    }

    /**
     * Envía email al `email_contact` configurado en settings.
     */
    protected function notifyAdmin(Inquiry $inquiry): void
    {
        try {
            $adminEmail = Setting::get('email_contact');
            if ($adminEmail && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                Notification::route('mail', $adminEmail)
                    ->notify(new NewInquiryNotification($inquiry));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

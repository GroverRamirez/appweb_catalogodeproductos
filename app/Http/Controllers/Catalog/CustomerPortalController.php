<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\WishlistItem;
use App\Services\ExchangeRate;
use App\Services\ReceiptPdf;
use App\Support\AdminGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CustomerPortalController extends Controller
{
    /**
     * Panel "Mis Pedidos": visualización de consultas, compras y favoritos.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();
        $rates = app(ExchangeRate::class);

        // Si es miembro del staff y quiere ver el portal, lo permitimos o se resuelve su ficha
        $client = Client::where('user_id', $user->id)->first();

        // Enlazar consultas o ventas huérfanas que coincidan con el email o teléfono
        if ($client) {
            Inquiry::whereNull('cliente_id')
                ->where(function ($q) use ($user, $client) {
                    $q->where('cliente_email', $user->email);
                    if ($client->phone) {
                        $q->orWhere('cliente_telefono', $client->phone);
                    }
                })
                ->update(['cliente_id' => $client->id]);
        }

        // Consultas / Pedidos del cliente
        $inquiries = $client
            ? Inquiry::query()
                ->where('cliente_id', $client->id)
                ->with(['items:id,consulta_id,producto_id,producto_nombre_copia,producto_codigo_copia,cantidad,precio_unitario'])
                ->latest('id')
                ->get()
                ->map(fn (Inquiry $inq) => [
                    'id' => $inq->id,
                    'public_token' => $inq->public_token,
                    'status' => $inq->status,
                    'payment_status' => $inq->payment_status,
                    'payment_receipt_url' => $inq->payment_receipt_url,
                    'payment_notes' => $inq->payment_notes,
                    'source' => $inq->source,
                    'total_estimated' => (float) $inq->total_estimated,
                    'discount_amount' => (float) ($inq->discount_amount ?? 0),
                    'coupon_code' => $inq->coupon_code,
                    'created_at' => $inq->created_at->format('d/m/Y H:i'),
                    'items' => $inq->items->map(fn ($it) => [
                        'id' => $it->id,
                        'name' => $it->producto_nombre_copia,
                        'code' => $it->producto_codigo_copia,
                        'quantity' => (int) $it->cantidad,
                        'unit_price' => (float) $it->precio_unitario,
                        'subtotal' => (float) ($it->cantidad * $it->precio_unitario),
                    ]),
                ])
            : collect();

        // Compras confirmadas del cliente
        $sales = $client
            ? Sale::query()
                ->where('cliente_id', $client->id)
                ->with(['items:id,venta_id,producto_id,producto_nombre_copia,producto_codigo_copia,cantidad,precio_unitario'])
                ->latest('id')
                ->get()
                ->map(fn (Sale $sale) => [
                    'id' => $sale->id,
                    'receipt_number' => str_pad((string) $sale->numero_recibo, 6, '0', STR_PAD_LEFT),
                    'status' => $sale->status,
                    'payment_method' => $sale->payment_method,
                    'subtotal' => (float) $sale->subtotal,
                    'discount_amount' => (float) ($sale->discount_amount ?? 0),
                    'total' => (float) $sale->total,
                    'created_at' => $sale->created_at->format('d/m/Y H:i'),
                    'items_count' => $sale->items->sum('cantidad'),
                    'receipt_url' => route('customer.sales.receipt', $sale),
                    'items' => $sale->items->map(fn ($it) => [
                        'id' => $it->id,
                        'name' => $it->producto_nombre_copia,
                        'code' => $it->producto_codigo_copia,
                        'quantity' => (int) $it->cantidad,
                        'unit_price' => (float) $it->precio_unitario,
                    ]),
                ])
            : collect();

        // Favoritos sincronizados
        $wishlist = $user->wishlistProducts()
            ->active()
            ->with(['mainImage'])
            ->get()
            ->map(fn (Product $p) => [
                'product_id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'code' => $p->code,
                'price' => (float) $rates->toBs($p->sale_price ?: $p->price),
                'stock' => (int) $p->stock,
                'image' => $p->mainImage?->thumbnail_url ?? $p->mainImage?->url,
            ]);

        return Inertia::render('catalog/CustomerPortal', [
            'client' => $client ? [
                'name' => $client->name,
                'id_card' => $client->id_card,
                'phone' => $client->phone,
                'email' => $client->email ?: $user->email,
                'address' => $client->address,
            ] : [
                'name' => $user->name,
                'id_card' => null,
                'phone' => null,
                'email' => $user->email,
                'address' => null,
            ],
            'inquiries' => $inquiries,
            'sales' => $sales,
            'wishlist' => $wishlist,
            'storePhone' => Setting::get('whatsapp_number', ''),
        ]);
    }

    /**
     * Descarga del comprobante de venta en PDF para el cliente.
     */
    public function receipt(Request $request, Sale $sale): Response
    {
        $user = $request->user();
        $isStaff = AdminGuard::canAccessAdmin($user);

        // Si es staff o es el dueño de la compra
        $client = Client::where('user_id', $user->id)->first();
        $isOwner = $client && $sale->cliente_id === $client->id;

        abort_unless($isStaff || $isOwner, 403, 'No tienes permiso para descargar este comprobante.');

        $pdf = ReceiptPdf::make($sale);

        return $request->boolean('inline')
            ? $pdf->stream(ReceiptPdf::filename($sale))
            : $pdf->download(ReceiptPdf::filename($sale));
    }

    /**
     * Sincroniza la lista de favoritos de localStorage con la base de datos.
     */
    public function syncWishlist(Request $request): JsonResponse
    {
        $user = $request->user();
        $rates = app(ExchangeRate::class);

        $request->validate([
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:productos,id'],
        ]);

        $incomingIds = $request->input('product_ids', []);

        if (! empty($incomingIds)) {
            $existingIds = $user->wishlistItems()->pluck('producto_id')->all();
            $toInsert = array_diff($incomingIds, $existingIds);

            foreach ($toInsert as $productId) {
                WishlistItem::firstOrCreate([
                    'user_id' => $user->id,
                    'product_id' => $productId,
                ]);
            }
        }

        $products = $user->wishlistProducts()
            ->active()
            ->with(['mainImage'])
            ->get();

        return response()->json([
            'items' => $products->map(fn (Product $p) => [
                'product_id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'code' => $p->code,
                'price' => (float) $rates->toBs($p->sale_price ?: $p->price),
                'stock' => (int) $p->stock,
                'image' => $p->mainImage?->thumbnail_url ?? $p->mainImage?->url,
            ]),
        ]);
    }

    /**
     * Alterna (toggle) un producto en los favoritos de la base de datos.
     */
    public function toggleWishlist(Request $request, Product $product): JsonResponse
    {
        $user = $request->user();

        $existing = WishlistItem::where('user_id', $user->id)
            ->where('producto_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
        } else {
            WishlistItem::firstOrCreate([
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);
            $inWishlist = true;
        }

        return response()->json([
            'in_wishlist' => $inWishlist,
            'count' => $user->wishlistItems()->count(),
        ]);
    }

    /**
     * Sube el comprobante de pago para un pedido del cliente autenticado.
     */
    public function uploadReceipt(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $user = $request->user();
        $client = Client::where('user_id', $user->id)->first();

        if (! $client || $inquiry->cliente_id !== $client->id) {
            abort(403, 'No tienes permiso para subir comprobante a este pedido.');
        }

        $request->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'receipt.required' => 'Debes seleccionar un archivo.',
            'receipt.file' => 'El archivo subido no es válido.',
            'receipt.mimes' => 'El comprobante debe ser una imagen (JPG, PNG, WebP) o un documento PDF.',
            'receipt.max' => 'El comprobante no debe superar los 5 MB.',
        ]);

        if ($inquiry->comprobante_pago) {
            Storage::disk('public')->delete($inquiry->comprobante_pago);
        }

        $file = $request->file('receipt');
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = 'recibo_'.$inquiry->id.'_'.Str::random(12).'.'.$extension;
        $path = $file->storeAs('comprobantes_pago', $filename, 'public');

        $inquiry->update([
            'payment_receipt_path' => $path,
            'payment_status' => 'comprobante_subido',
        ]);

        $inquiry->notes()->create([
            'user_id' => $user->id,
            'body' => 'El cliente subió el comprobante de pago desde Mis Pedidos.',
        ]);

        return back()->with('success', '¡Comprobante de pago subido correctamente! Validaremos tu transferencia a la brevedad.');
    }
}

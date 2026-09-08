<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInquiryNoteRequest;
use App\Http\Requests\Admin\UpdateInquiryRequest;
use App\Models\Inquiry;
use App\Models\Sale;
use App\Support\CsvDownload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InquiryController extends Controller
{
    public function index(Request $request): Response
    {
        $inquiries = Inquiry::query()
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('estado', $request->string('status')))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(function ($qq) use ($term) {
                    $qq->where('cliente_nombre', 'like', "%$term%")
                        ->orWhere('cliente_telefono', 'like', "%$term%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/inquiries/Index', [
            'inquiries' => $inquiries,
            'filters' => $request->only(['q', 'status']),
            'statuses' => Inquiry::STATUSES,
        ]);
    }

    public function show(Inquiry $inquiry): Response
    {
        $inquiry->load(['items.product:id,nombre,codigo', 'handler:id,name', 'notes.author:id,name', 'sales:id,consulta_id,numero_recibo,estado,total', 'client:id,nombre,telefono']);

        return Inertia::render('admin/inquiries/Show', [
            'inquiry' => $inquiry,
            'statuses' => Inquiry::STATUSES,
        ]);
    }

    public function update(UpdateInquiryRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $data = $request->validated();

        $previousStatus = $inquiry->status;

        $inquiry->fill($data);
        if ($inquiry->status !== 'pendiente' && ! $inquiry->contacted_at) {
            $inquiry->contacted_at = now();
            $inquiry->handled_by = $request->user()->id;
        }
        $inquiry->save();

        // Dejar rastro del cambio de estado en el historial de seguimiento.
        if ($inquiry->status !== $previousStatus) {
            $inquiry->notes()->create([
                'user_id' => $request->user()->id,
                'body' => "Cambió el estado de \"{$previousStatus}\" a \"{$inquiry->status}\".",
            ]);
        }

        $negatives = $this->syncStockWithStatus($inquiry, $previousStatus, $request->user()->id);

        if ($negatives !== []) {
            // Canal de toast del proyecto: soporta el tipo "warning" y ya se
            // renderiza (resources/js/lib/flashToast.ts). Un ->with('warning')
            // no se mostraria: solo se comparten success y error.
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Stock negativo en: '.implode(', ', $negatives).
                    '. Revisá el inventario de esos productos.',
            ]);
        }

        return back()->with('success', 'Consulta actualizada.');
    }

    public function storeNote(StoreInquiryNoteRequest $request, Inquiry $inquiry): RedirectResponse
    {
        $inquiry->notes()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return back()->with('success', 'Nota agregada.');
    }

    /**
     * Sincroniza la venta según la transición de estado de la consulta.
     *
     * Marcar "vendido" genera una venta (que es la que descuenta el stock);
     * revertir ese estado la anula (y el stock vuelve). Solo actúa en la
     * transición (entrar o salir de "vendido"), así que guardar dos veces
     * seguidas no genera dos ventas.
     *
     * El stock no se mueve acá a propósito: la venta es la única fuente de
     * verdad de salidas de mercadería e ingresos, y los reportes leen de ella.
     *
     * @return array<int, string> códigos de productos que quedaron en negativo
     */
    private function syncStockWithStatus(Inquiry $inquiry, string $previousStatus, int $userId): array
    {
        $wasSold = $previousStatus === 'vendido';
        $isSold = $inquiry->status === 'vendido';

        if ($wasSold === $isSold) {
            return [];
        }

        return $isSold
            ? $this->createSaleFromInquiry($inquiry, $userId)
            : $this->voidSaleFromInquiry($inquiry, $userId);
    }

    /**
     * Genera la venta de una consulta recién marcada como vendida.
     *
     * El stock no se recorta en 0: un stock negativo es la señal honesta de que
     * el inventario cargado no coincide con lo que realmente había. Es el mismo
     * criterio que usa la anulación de compras.
     *
     * @return array<int, string> códigos de productos que quedaron en negativo
     */
    private function createSaleFromInquiry(Inquiry $inquiry, int $userId): array
    {
        // Si ya tiene una venta viva, no se duplica: puede pasar si el estado
        // se cambió a mano yendo y viniendo.
        if ($inquiry->sales()->where('estado', 'confirmada')->exists()) {
            return [];
        }

        $lines = $inquiry->items()
            ->get()
            ->filter(fn ($item) => $item->product_id !== null)
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
            ])
            ->values()
            ->all();

        if ($lines === []) {
            $inquiry->notes()->create([
                'user_id' => $userId,
                'body' => 'No se generó venta: la consulta no tiene productos del catálogo.',
            ]);

            return [];
        }

        $negatives = [];

        $sale = DB::transaction(function () use ($inquiry, $lines, $userId, &$negatives) {
            $sale = Sale::create([
                'receipt_number' => Sale::nextReceiptNumber(),
                'inquiry_id' => $inquiry->id,
                'client_id' => $inquiry->client_id,
                'origin' => 'consulta',
                'customer_name' => $inquiry->customer_name,
                'customer_phone' => $inquiry->customer_phone,
                'payment_method' => 'efectivo',
                'status' => 'confirmada',
                'created_by' => $userId,
            ]);

            $subtotal = SaleController::applyItems($sale, $lines, $negatives);

            // El descuento del cupón viaja con la consulta. Se recorta al
            // subtotal por si los precios cambiaron desde que se pidió.
            $discount = min((float) ($inquiry->discount_amount ?? 0), $subtotal);

            $sale->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total' => $subtotal - $discount,
            ]);

            return $sale;
        });

        $inquiry->notes()->create([
            'user_id' => $userId,
            'body' => 'Se generó la venta N.º '.str_pad((string) $sale->receipt_number, 6, '0', STR_PAD_LEFT).
                ' y se descontó el stock.',
        ]);

        return $negatives;
    }

    /**
     * Anula la venta de una consulta que dejó de estar vendida, devolviendo el
     * stock que había descontado.
     *
     * @return array<int, string> siempre vacío: devolver stock nunca lo deja negativo
     */
    private function voidSaleFromInquiry(Inquiry $inquiry, int $userId): array
    {
        $sale = $inquiry->sales()->where('estado', 'confirmada')->first();

        if (! $sale) {
            return [];
        }

        DB::transaction(function () use ($sale, $userId) {
            SaleController::restoreStock($sale);

            $sale->update([
                'status' => 'anulada',
                'voided_by' => $userId,
                'voided_at' => now(),
            ]);
        });

        $inquiry->notes()->create([
            'user_id' => $userId,
            'body' => 'Se anuló la venta N.º '.str_pad((string) $sale->receipt_number, 6, '0', STR_PAD_LEFT).
                ' y se devolvió el stock.',
        ]);

        return [];
    }

    public function destroy(Inquiry $inquiry): RedirectResponse
    {
        $inquiry->delete();

        return to_route('admin.inquiries.index')->with('success', 'Consulta eliminada.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Inquiry::query()
            ->with('items:id,consulta_id,producto_nombre_copia,producto_codigo_copia,cantidad,precio_unitario')
            ->when($request->filled('status'), fn ($q) => $q->where('estado', $request->string('status')))
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where(function ($qq) use ($term) {
                    $qq->where('cliente_nombre', 'like', "%$term%")
                        ->orWhere('cliente_telefono', 'like', "%$term%");
                });
            })
            ->latest();

        $headers = [
            'ID', 'Fecha', 'Cliente', 'Teléfono', 'Email',
            'Origen', 'Estado', 'Total estimado', 'Items', 'Notas',
        ];

        $rows = function () use ($query) {
            foreach ($query->cursor() as $inq) {
                $items = $inq->items
                    ->map(fn ($i) => "{$i->quantity}x {$i->product_name_snapshot} ({$i->product_code_snapshot})")
                    ->implode(' | ');
                yield [
                    $inq->id,
                    optional($inq->created_at)->format('Y-m-d H:i'),
                    $inq->customer_name,
                    $inq->customer_phone,
                    $inq->customer_email,
                    $inq->source,
                    $inq->status,
                    $inq->total_estimated,
                    $items,
                    $inq->admin_notes,
                ];
            }
        };

        return CsvDownload::stream(
            'consultas-'.now()->format('Y-m-d').'.csv',
            $headers,
            $rows(),
        );
    }
}

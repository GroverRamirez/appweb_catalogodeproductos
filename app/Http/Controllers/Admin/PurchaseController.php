<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $purchases = Purchase::query()
            ->with('supplier:id,nombre')
            ->withCount('items')
            ->when($request->string('q')->toString(), function ($q, $term) {
                $q->where('numero_referencia', 'like', "%$term%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('nombre', 'like', "%$term%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('estado', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/purchases/Index', [
            'purchases' => $purchases,
            'filters' => $request->only(['q', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/purchases/Create', [
            'suppliers' => Supplier::query()->active()->orderBy('nombre')->get(['id', 'nombre']),
            'products' => Product::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'costo', 'stock']),
        ]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'confirmada',
                'created_by' => auth()->id(),
            ]);

            $total = 0;

            foreach ($data['items'] as $row) {
                // Bloquea la fila del producto para evitar carreras si dos
                // compras del mismo producto se confirman a la vez.
                $product = Product::query()->lockForUpdate()->findOrFail($row['product_id']);

                $qty = (int) $row['quantity'];
                $unitCost = (float) $row['unit_cost'];
                $total += $qty * $unitCost;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'product_code_snapshot' => $product->code,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                ]);

                // La compra confirmada suma stock de inmediato y actualiza el
                // costo del producto al de esta última compra (no es
                // promedio ponderado, es una simplificación deliberada).
                $product->update([
                    'stock' => $product->stock + $qty,
                    'cost' => $unitCost,
                ]);
            }

            $purchase->update(['total_cost' => $total]);
        });

        return to_route('admin.purchases.index')->with('success', 'Compra registrada y stock actualizado.');
    }

    public function show(Purchase $purchase): Response
    {
        $purchase->load(['supplier', 'items', 'creator:id,name', 'voider:id,name']);

        return Inertia::render('admin/purchases/Show', [
            'purchase' => $purchase,
        ]);
    }

    /**
     * Anula una compra confirmada y revierte el stock que había sumado.
     * No se recorta a 0: si el producto ya se vendió por debajo de lo
     * comprado, el stock queda negativo a propósito, como aviso real de que
     * hay que revisar el inventario.
     */
    public function void(Purchase $purchase): RedirectResponse
    {
        abort_unless($purchase->status === 'confirmada', 422, 'Esta compra ya está anulada.');

        DB::transaction(function () use ($purchase) {
            foreach ($purchase->items as $item) {
                if ($item->product_id) {
                    Product::query()
                        ->whereKey($item->product_id)
                        ->lockForUpdate()
                        ->first()
                        ?->decrement('stock', $item->quantity);
                }
            }

            $purchase->update([
                'status' => 'anulada',
                'voided_by' => auth()->id(),
                'voided_at' => now(),
            ]);
        });

        return to_route('admin.purchases.show', $purchase)->with('success', 'Compra anulada y stock revertido.');
    }
}

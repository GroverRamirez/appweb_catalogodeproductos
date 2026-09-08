<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
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
                // El OR va agrupado: sin el where() envolvente, SQL lo lee como
                // "ref LIKE ? OR (proveedor AND estado = ?)" y el filtro de
                // estado deja de aplicarse a lo que matchea por referencia.
                $q->where(function ($group) use ($term) {
                    $group->where('numero_referencia', 'like', "%$term%")
                        ->orWhereHas('supplier', fn ($s) => $s->where('nombre', 'like', "%$term%"));
                });
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

    /**
     * Avisa si ya existe una compra con el mismo numero de remito para el mismo
     * proveedor. Es solo un aviso: el proveedor puede repetir numeracion entre
     * anios, asi que no se bloquea, se informa para que el usuario decida.
     */
    public function referenceCheck(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'supplier_id' => ['nullable', 'integer'],
        ]);

        $existing = Purchase::query()
            ->where('numero_referencia', $request->string('reference')->toString())
            ->where('proveedor_id', $request->input('supplier_id'))
            ->where('estado', 'confirmada')
            ->latest()
            ->first(['id', 'created_at']);

        return response()->json([
            'duplicate' => $existing !== null,
            'purchase_id' => $existing?->id,
            'created_at' => $existing?->created_at?->toDateString(),
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

                // Se guarda el costo vigente ANTES de pisarlo, para poder
                // devolverlo si la compra se anula. Solo tiene sentido cuando
                // esta linea efectivamente lo cambia (ver mas abajo).
                $previousCost = $unitCost > 0 ? $product->cost : null;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'product_code_snapshot' => $product->code,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'previous_cost' => $previousCost,
                ]);

                // La compra confirmada suma stock de inmediato y actualiza el
                // costo del producto al de esta última compra (no es
                // promedio ponderado, es una simplificación deliberada).
                //
                // Un costo 0 NUNCA pisa el costo conocido: existen ingresos sin
                // cargo (bonificaciones, reposición por garantía), y dejarlos
                // sobrescribir borraría un dato real y sacaría al producto de
                // la valorización del inventario.
                $changes = ['stock' => $product->stock + $qty];

                if ($unitCost > 0) {
                    $changes['cost'] = $unitCost;
                }

                $product->update($changes);
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
     * Anula una compra confirmada: revierte el stock que había sumado y, cuando
     * es seguro, devuelve el costo que el producto tenía antes.
     *
     * El stock no se recorta a 0: si el producto ya se vendió por debajo de lo
     * comprado, queda negativo a propósito, como aviso real de que hay que
     * revisar el inventario.
     */
    public function void(Purchase $purchase): RedirectResponse
    {
        abort_unless($purchase->status === 'confirmada', 422, 'Esta compra ya está anulada.');

        $restoredCosts = 0;

        DB::transaction(function () use ($purchase, &$restoredCosts) {
            foreach ($purchase->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::query()
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    continue;
                }

                $changes = ['stock' => $product->stock - (int) $item->quantity];

                // El costo solo se devuelve si el que está vigente es el que
                // puso esta compra. Si otra compra posterior ya lo cambió,
                // pisarlo con un valor viejo seria peor que dejarlo como esta.
                $setByThisPurchase = (float) $product->cost === (float) $item->unit_cost;

                if ($item->previous_cost !== null && $setByThisPurchase) {
                    $changes['cost'] = (float) $item->previous_cost;
                    $restoredCosts++;
                }

                $product->update($changes);
            }

            $purchase->update([
                'status' => 'anulada',
                'voided_by' => auth()->id(),
                'voided_at' => now(),
            ]);
        });

        $message = $restoredCosts > 0
            ? "Compra anulada. Se revirtió el stock y el costo de {$restoredCosts} producto(s)."
            : 'Compra anulada y stock revertido.';

        return to_route('admin.purchases.show', $purchase)->with('success', $message);
    }
}

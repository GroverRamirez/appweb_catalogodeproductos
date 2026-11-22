<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePurchaseRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\InventoryLedger;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseController extends Controller
{
    public function index(Request $request): Response
    {
        $purchases = Purchase::query()
            ->with('supplier:id,nombre,contacto_nombre')
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

    public function create(Request $request): Response
    {
        $initialSupplierId = $request->integer('supplier_id') ?: null;
        $initialItems = [];
        if ($request->filled('items')) {
            $rawItems = json_decode($request->string('items')->toString(), true);
            if (is_array($rawItems)) {
                foreach ($rawItems as $item) {
                    if (isset($item['product_id'])) {
                        $initialItems[] = [
                            'product_id' => (int) $item['product_id'],
                            'quantity' => max((int) ($item['quantity'] ?? 1), 1),
                            'unit_cost' => isset($item['unit_cost']) && is_numeric($item['unit_cost']) ? (float) $item['unit_cost'] : '',
                        ];
                    }
                }
            }
        }

        return Inertia::render('admin/purchases/Create', [
            'suppliers' => Supplier::query()->active()->orderBy('nombre')->get(['id', 'nombre']),
            'products' => Product::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'costo', 'stock']),
            'initialSupplierId' => $initialSupplierId,
            'initialItems' => $initialItems,
        ]);
    }

    /**
     * Vista de sugerencia de reposición agrupada por proveedor para productos
     * con stock en o por debajo del stock mínimo.
     */
    public function suggested(): Response
    {
        $data = $this->getSuggestedPurchasesData();

        return Inertia::render('admin/purchases/Suggested', [
            'groups' => $data['groups'],
            'totalProducts' => $data['total_products'],
            'totalUnits' => $data['total_units'],
            'totalCost' => $data['total_cost'],
        ]);
    }

    /**
     * Exporta la orden de reposición sugerida en formato CSV (global o por proveedor).
     */
    public function exportSuggestedCsv(Request $request): StreamedResponse
    {
        $data = $this->getSuggestedPurchasesData();
        $supplierFilter = $request->integer('supplier_id') ?: null;

        $filename = 'orden_compra_sugerida_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($data, $supplierFilter) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Proveedor',
                'Código SKU',
                'Producto',
                'Stock Actual',
                'Stock Mínimo',
                'Cantidad Sugerida',
                'Costo Unitario Estimado (USD)',
                'Subtotal Estimado (USD)',
            ]);

            foreach ($data['groups'] as $group) {
                if ($supplierFilter && $group['supplier']['id'] !== $supplierFilter) {
                    continue;
                }

                foreach ($group['items'] as $item) {
                    fputcsv($handle, [
                        $group['supplier']['name'],
                        $item['code'],
                        $item['name'],
                        $item['stock'],
                        $item['min_stock'],
                        $item['suggested_quantity'],
                        number_format($item['unit_cost'], 2, '.', ''),
                        number_format($item['subtotal'], 2, '.', ''),
                    ]);
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Calcula productos con stock bajo y los agrupa por su último proveedor conocido.
     *
     * @return array{
     *     groups: array<int, array{
     *         supplier: array{id: int|null, name: string, phone: string|null, email: string|null},
     *         items: array<int, array{
     *             product_id: int,
     *             code: string,
     *             name: string,
     *             stock: int,
     *             min_stock: int,
     *             suggested_quantity: int,
     *             unit_cost: float,
     *             subtotal: float
     *         }>,
     *         total_suggested_units: int,
     *         total_estimated_cost: float
     *     }>,
     *     total_products: int,
     *     total_units: int,
     *     total_cost: float
     * }
     */
    protected function getSuggestedPurchasesData(): array
    {
        $lowStockProducts = Product::query()
            ->where('stock', '<=', DB::raw('stock_minimo'))
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'stock', 'stock_minimo', 'costo']);

        if ($lowStockProducts->isEmpty()) {
            return [
                'groups' => [],
                'total_products' => 0,
                'total_units' => 0,
                'total_cost' => 0.0,
            ];
        }

        // Buscar el último proveedor y costo de compra confirmado para cada producto
        $lastPurchases = DB::table('compra_items')
            ->join('compras', 'compras.id', '=', 'compra_items.compra_id')
            ->join('proveedores', 'proveedores.id', '=', 'compras.proveedor_id')
            ->whereIn('compra_items.producto_id', $lowStockProducts->pluck('id'))
            ->where('compras.estado', 'confirmada')
            ->select(
                'compra_items.producto_id',
                'proveedores.id as supplier_id',
                'proveedores.nombre as supplier_name',
                'proveedores.telefono as supplier_phone',
                'proveedores.email as supplier_email',
                'compra_items.costo_unitario',
                'compras.created_at'
            )
            ->orderByDesc('compras.created_at')
            ->get()
            ->unique('producto_id')
            ->keyBy('producto_id');

        $groups = [];
        $totalUnits = 0;
        $totalCost = 0.0;

        foreach ($lowStockProducts as $product) {
            $last = $lastPurchases->get($product->id);
            $supplierId = $last?->supplier_id;
            $groupKey = $supplierId !== null ? (string) $supplierId : 'unassigned';

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'supplier' => [
                        'id' => $supplierId,
                        'name' => $last?->supplier_name ?? 'Sin proveedor habitual',
                        'phone' => $last?->supplier_phone ?? null,
                        'email' => $last?->supplier_email ?? null,
                    ],
                    'items' => [],
                    'total_suggested_units' => 0,
                    'total_estimated_cost' => 0.0,
                ];
            }

            // Cantidad sugerida: reponer hasta 2x el stock mínimo (o mínimo 5)
            $target = $product->stock_minimo > 0 ? $product->stock_minimo * 2 : 5;
            $suggestedQty = max($target - $product->stock, 1);

            $cost = $last ? (float) $last->costo_unitario : ((float) $product->costo ?: 0.0);
            $subtotal = $suggestedQty * $cost;

            $groups[$groupKey]['items'][] = [
                'product_id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'stock' => $product->stock,
                'min_stock' => $product->min_stock,
                'suggested_quantity' => $suggestedQty,
                'unit_cost' => $cost,
                'subtotal' => $subtotal,
            ];

            $groups[$groupKey]['total_suggested_units'] += $suggestedQty;
            $groups[$groupKey]['total_estimated_cost'] += $subtotal;

            $totalUnits += $suggestedQty;
            $totalCost += $subtotal;
        }

        return [
            'groups' => array_values($groups),
            'total_products' => $lowStockProducts->count(),
            'total_units' => $totalUnits,
            'total_cost' => round($totalCost, 2),
        ];
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

        $purchase = DB::transaction(function () use ($data) {
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
                InventoryLedger::record($product, $qty, InventoryMovement::TYPE_PURCHASE, $purchase);

                if ($unitCost > 0) {
                    $product->update(['cost' => $unitCost]);
                }
            }

            $purchase->update(['total_cost' => $total]);

            return $purchase;
        });

        $units = (int) collect($data['items'])->sum('quantity');

        return to_route('admin.purchases.index')->with('success', [
            'message' => $purchase->supplier
                ? "Compra a {$purchase->supplier->name} registrada"
                : 'Compra registrada',
            'description' => "Ingresaron {$units} ".($units === 1 ? 'unidad' : 'unidades').
                ' al stock. Total: USD '.number_format((float) $purchase->total_cost, 2, ',', '.').'.',
        ]);
    }

    public function show(Purchase $purchase): Response
    {
        $purchase->load(['supplier', 'items', 'creator:id,name', 'voider:id,name']);

        $qrSvg = null;
        try {
            $renderer = new ImageRenderer(
                new RendererStyle(110, 1),
                new SvgImageBackEnd
            );
            $writer = new Writer($renderer);
            $purchaseUrl = url("/admin/purchases/{$purchase->id}");
            $qrSvg = $writer->writeString($purchaseUrl);
        } catch (\Throwable $e) {
            report($e);
        }

        return Inertia::render('admin/purchases/Show', [
            'purchase' => $purchase,
            'qrSvg' => $qrSvg,
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
        $restoredCosts = 0;

        DB::transaction(function () use ($purchase, &$restoredCosts) {
            // Mismo criterio que la anulación de ventas: se relee con la fila
            // bloqueada y el estado se verifica adentro. Verificarlo afuera
            // permitía que dos pedidos simultáneos revirtieran el stock dos
            // veces.
            $locked = Purchase::query()->whereKey($purchase->getKey())->lockForUpdate()->first();

            abort_unless($locked?->status === 'confirmada', 422, 'Esta compra ya está anulada.');

            foreach ($locked->items as $item) {
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

                // El costo solo se devuelve si el que está vigente es el que
                // puso esta compra. Si otra compra posterior ya lo cambió,
                // pisarlo con un valor viejo seria peor que dejarlo como esta.
                $setByThisPurchase = (float) $product->cost === (float) $item->unit_cost;

                if ($item->previous_cost !== null && $setByThisPurchase) {
                    $product->update(['cost' => (float) $item->previous_cost]);
                    $restoredCosts++;
                }

                InventoryLedger::record(
                    $product,
                    -(int) $item->quantity,
                    InventoryMovement::TYPE_PURCHASE_VOID,
                    $locked,
                );
            }

            $locked->update([
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

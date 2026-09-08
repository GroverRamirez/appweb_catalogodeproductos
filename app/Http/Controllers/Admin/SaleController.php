<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSaleRequest;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $sales = Sale::query()
            ->withCount('items')
            ->when($request->string('q')->toString(), function ($q, $term) {
                // El OR va agrupado: sin el where() envolvente, SQL lo lee como
                // "recibo LIKE ? OR (cliente AND estado = ?)" y el filtro de
                // estado deja de aplicarse a lo que matchea por recibo.
                $q->where(function ($group) use ($term) {
                    $group->where('numero_recibo', 'like', "%$term%")
                        ->orWhere('cliente_nombre', 'like', "%$term%")
                        ->orWhere('cliente_telefono', 'like', "%$term%");
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('estado', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sales/Index', [
            'sales' => $sales,
            'filters' => $request->only(['q', 'status']),
            'statuses' => Sale::STATUSES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sales/Create', [
            'products' => Product::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'precio', 'precio_oferta', 'costo', 'stock']),
            'paymentMethods' => Sale::PAYMENT_METHODS,
        ]);
    }

    /**
     * Registra una venta de mostrador: descuenta stock y congela el costo de
     * cada producto en la línea.
     *
     * El stock no se recorta a 0: si se vende más de lo cargado, queda negativo
     * a propósito, como aviso real de que el inventario no coincide con la
     * realidad. Es el mismo criterio de la anulación de compras.
     */
    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $negatives = [];

        $sale = DB::transaction(function () use ($data, $request, &$negatives) {
            // El cliente se resuelve por teléfono: si ya compró antes se reusa
            // su ficha, si no se crea. Sin teléfono la venta queda sin cliente
            // (consumidor final), que es lo normal en la venta al paso.
            $client = Client::resolveByPhone($data['customer_phone'] ?? null, [
                'name' => ($data['customer_name'] ?? null) ?: 'Cliente sin nombre',
                'id_card' => $data['id_card'] ?? null,
            ]);

            $sale = Sale::create([
                'receipt_number' => Sale::nextReceiptNumber(),
                'origin' => 'mostrador',
                'client_id' => $client?->id,
                // Snapshot: el comprobante tiene que seguir diciendo lo que
                // decía aunque el cliente cambie de datos más adelante.
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'payment_method' => $data['payment_method'],
                'notes' => $data['notes'] ?? null,
                'status' => 'confirmada',
                'created_by' => $request->user()->id,
            ]);

            $subtotal = $this->applyItems($sale, $data['items'], $negatives);
            $discount = (float) ($data['discount_amount'] ?? 0);

            // Recién acá se puede validar el descuento: el subtotal se calcula
            // desde la base, nunca se acepta del cliente.
            if ($discount > $subtotal) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'El descuento no puede superar el subtotal de la venta.',
                ]);
            }

            $sale->update([
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'total' => $subtotal - $discount,
            ]);

            return $sale;
        });

        static::warnAboutNegativeStock($negatives);

        return to_route('admin.sales.show', $sale)->with('success', 'Venta registrada y stock actualizado.');
    }

    public function show(Sale $sale): Response
    {
        $sale->load([
            'items', 'creator:id,name', 'voider:id,name',
            'inquiry:id,cliente_nombre',
            'client:id,nombre,telefono,carnet_identidad',
        ]);

        return Inertia::render('admin/sales/Show', [
            'sale' => $sale,
        ]);
    }

    /**
     * Anula una venta confirmada y devuelve al stock lo que había descontado.
     * Una venta no se edita: se anula y se registra otra.
     */
    public function void(Sale $sale): RedirectResponse
    {
        abort_unless($sale->status === 'confirmada', 422, 'Esta venta ya está anulada.');

        DB::transaction(function () use ($sale) {
            static::restoreStock($sale);

            $sale->update([
                'status' => 'anulada',
                'voided_by' => auth()->id(),
                'voided_at' => now(),
            ]);
        });

        return to_route('admin.sales.show', $sale)->with('success', 'Venta anulada y stock devuelto.');
    }

    /**
     * Crea las líneas de una venta, descuenta el stock y devuelve el subtotal
     * calculado desde la base. Compartido por la venta de mostrador y por la
     * venta que nace de una consulta marcada como vendida.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, string>  $negatives  se llena con los códigos que quedaron en negativo
     */
    public static function applyItems(Sale $sale, array $items, array &$negatives): float
    {
        $subtotal = 0.0;

        foreach ($items as $row) {
            // Bloquea la fila del producto para evitar carreras si dos ventas
            // del mismo producto se confirman a la vez.
            // withTrashed: un producto retirado del catalogo puede seguir
            // apareciendo en una consulta vieja, y su stock sigue siendo real.
            // Sin esto, vender esa linea explotaria con un 404.
            $product = Product::withTrashed()->lockForUpdate()->findOrFail($row['product_id']);

            $qty = (int) $row['quantity'];
            $unitPrice = (float) $row['unit_price'];
            $subtotal += $qty * $unitPrice;

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'product_name_snapshot' => $product->name,
                'product_code_snapshot' => $product->code,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                // Congelado al vender: el costo del producto cambia con cada
                // compra posterior y consultarlo después daría margen falso.
                'unit_cost' => $product->cost,
            ]);

            $product->update(['stock' => $product->stock - $qty]);

            if ($product->stock < 0) {
                $negatives[] = $product->code;
            }
        }

        return $subtotal;
    }

    /**
     * Devuelve al stock lo que las líneas de una venta habían descontado.
     * Se llama siempre dentro de una transacción.
     */
    public static function restoreStock(Sale $sale): void
    {
        foreach ($sale->items()->get() as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::withTrashed()
                ->whereKey($item->product_id)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                continue;
            }

            $product->update(['stock' => $product->stock + (int) $item->quantity]);
        }
    }

    /**
     * Avisa por el canal de toast del proyecto (soporta el tipo "warning" y ya
     * se renderiza). Un ->with('warning') no se mostraría: HandleInertiaRequests
     * solo comparte success y error.
     *
     * @param  array<int, string>  $negatives
     */
    public static function warnAboutNegativeStock(array $negatives): void
    {
        if ($negatives === []) {
            return;
        }

        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => 'Stock negativo en: '.implode(', ', $negatives).
                '. Revisá el inventario de esos productos.',
        ]);
    }
}

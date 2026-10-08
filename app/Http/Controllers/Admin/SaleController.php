<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSaleRequest;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Setting;
use App\Services\ExchangeRate;
use App\Services\InventoryLedger;
use Illuminate\Http\JsonResponse;
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
            // Sin `costo`: el formulario de venta sugiere el precio, no el
            // costo, así que mandarlo solo pondría el costo de todo el
            // catálogo en el HTML de quien registra una venta.
            // Los precios van convertidos a bolivianos: la venta se cobra en
            // Bs y el formulario no tiene que hacer cuentas de cambio.
            'products' => Product::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo', 'precio', 'precio_oferta', 'stock'])
                ->map(function (Product $p) {
                    $rates = app(ExchangeRate::class);

                    $p->setAttribute('precio', $rates->toBs($p->price));
                    $p->setAttribute('precio_oferta', $p->sale_price !== null
                        ? $rates->toBs($p->sale_price)
                        : null);

                    return $p;
                }),
            'paymentMethods' => Sale::PAYMENT_METHODS,
        ]);
    }

    /**
     * Busca la ficha del cliente por CI para autocompletar el formulario de
     * venta. Va con el permiso de registrar ventas, no con el de ver
     * clientes: es parte de vender y devuelve solo lo que el formulario usa.
     */
    public function clientLookup(Request $request): JsonResponse
    {
        $request->validate(['ci' => ['required', 'string', 'max:30']]);

        $client = Client::findByIdCard($request->string('ci')->toString());

        return response()->json([
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'id_card' => $client->id_card,
                'sales_count' => $client->sales()->count(),
            ] : null,
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
            // El cliente se resuelve por CI y, si no hay, por teléfono: si ya
            // compró antes se reusa su ficha, si no se crea. Sin CI ni
            // teléfono la venta queda sin cliente (consumidor final), que es
            // lo normal en la venta al paso.
            $client = Client::resolveForSale(
                $data['id_card'] ?? null,
                $data['customer_phone'] ?? null,
                $data['customer_name'] ?? null,
            );

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
                // Cotizacion del momento: el recibo tiene que seguir diciendo
                // lo mismo cuando el BCB publique otra manana.
                'exchange_rate' => app(ExchangeRate::class)->current(),
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

        return to_route('admin.sales.show', $sale)->with('success', [
            'message' => 'Venta N° '.str_pad((string) $sale->receipt_number, 6, '0', STR_PAD_LEFT).' registrada',
            'description' => 'Total: '.Setting::get('currency_symbol', 'Bs').' '.number_format((float) $sale->total, 2, ',', '.').
                '. El stock se actualizó.',
        ]);
    }

    public function show(Sale $sale): Response
    {
        $sale->load([
            // Las líneas van sin `costo_unitario`: el comprobante no lo
            // muestra y no hay motivo para mandar el costo de cada producto al
            // navegador de quien solo mira la venta.
            'items:id,venta_id,producto_id,producto_nombre_copia,producto_codigo_copia,cantidad,precio_unitario',
            'creator:id,name', 'voider:id,name',
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
        DB::transaction(function () use ($sale) {
            // La venta se relee con su fila bloqueada y recién ahí se verifica
            // el estado. Verificarlo antes de la transacción dejaba la puerta
            // abierta a que dos pedidos simultáneos —dos pestañas, o un
            // reintento después de un timeout— pasaran los dos el control y
            // devolvieran el stock dos veces.
            $locked = Sale::query()->whereKey($sale->getKey())->lockForUpdate()->first();

            abort_unless($locked?->status === 'confirmada', 422, 'Esta venta ya está anulada.');

            static::restoreStock($locked);

            $locked->update([
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
                // Se guarda en bolivianos, igual que el precio de la línea: el
                // costo de lista está en dólares y un margen que reste dólares
                // a bolivianos no significa nada.
                'unit_cost' => app(ExchangeRate::class)->toBs($product->cost, (float) $sale->exchange_rate ?: null),
            ]);

            InventoryLedger::record($product, -$qty, InventoryMovement::TYPE_SALE, $sale);

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

            InventoryLedger::record(
                $product,
                (int) $item->quantity,
                InventoryMovement::TYPE_SALE_VOID,
                $sale,
            );
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
                '. Revisa el inventario de esos productos.',
        ]);
    }
}

<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Datos del reporte gerencial para un rango de fechas.
 *
 * Una sola fuente de verdad para la pantalla, el CSV, el PDF y el Excel. Si
 * cada salida armara sus propias consultas, tarde o temprano el PDF diria una
 * cosa y la pantalla otra: justo el defecto que un reporte deberia ayudar a
 * evitar.
 */
class SalesReport
{
    /** Resultados ya calculados, para no repetir consultas en la misma salida. */
    private array $memo = [];

    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {}

    /**
     * Arma el reporte desde los filtros `from` / `to` de la peticion.
     *
     * El "hasta" se estira al final del dia: `$request->date()` devuelve las
     * 00:00 de esa fecha y, sin esto, el `whereBetween` dejaba afuera todo lo
     * que paso ese mismo dia — un reporte de un solo dia daba siempre cero y
     * "del 1 al 14" se comia el 14 entero.
     *
     * El `CarbonImmutable::parse` no es decorativo: con fechas mutables,
     * calcular `$start` a partir de `$end` mutaria `$end` y ambos extremos
     * terminarian en el mismo instante.
     */
    public static function fromRequest(Request $request): self
    {
        $end = CarbonImmutable::parse($request->date('to') ?? CarbonImmutable::now())->endOfDay();
        $start = CarbonImmutable::parse($request->date('from') ?? $end->subDays(29))->startOfDay();

        return new self($start, $end);
    }

    /** @return array{from: string, to: string} */
    public function range(): array
    {
        return [
            'from' => $this->start->format('Y-m-d'),
            'to' => $this->end->format('Y-m-d'),
        ];
    }

    /** Rango en texto, para el encabezado del PDF y del Excel. */
    public function label(): string
    {
        return 'Del '.$this->start->format('d/m/Y').' al '.$this->end->format('d/m/Y');
    }

    /** Nombre de archivo para las descargas: reporte-2026-09-01_2026-09-28.pdf */
    public function filename(string $extension): string
    {
        return 'reporte-'.$this->start->format('Y-m-d').'_'.$this->end->format('Y-m-d').'.'.$extension;
    }

    /** Consultas creadas dentro del rango. */
    private function inquiries(): Builder
    {
        return Inquiry::query()->whereBetween('created_at', [$this->start, $this->end]);
    }

    /**
     * Ventas confirmadas del rango. Las anuladas no son ingreso y no entran en
     * ningun indicador.
     */
    private function sales(): Builder
    {
        return Sale::query()
            ->where('estado', 'confirmada')
            ->whereBetween('created_at', [$this->start, $this->end]);
    }

    /** Memoiza el resultado de una consulta dentro de la misma instancia. */
    private function once(string $key, callable $callback): mixed
    {
        return $this->memo[$key] ??= $callback();
    }

    /**
     * Indicadores de cabecera.
     *
     * @return array<string, int|float>
     */
    public function kpis(): array
    {
        return $this->once('kpis', function () {
            $totalInquiries = $this->inquiries()->count();
            $sold = $this->inquiries()->where('estado', 'vendido')->count();
            $pending = $this->inquiries()->where('estado', 'pendiente')->count();
            $closed = $this->inquiries()->where('estado', 'cerrado')->count();

            // Los ingresos salen de las ventas confirmadas, no de las consultas:
            // el total estimado de una consulta es lo que el cliente pidio, no
            // lo que se cobro (no contempla descuento en mostrador ni cambio de
            // precio).
            $revenue = (float) $this->sales()->sum('total');
            $salesCount = $this->sales()->count();

            // Margen bruto con el costo congelado en cada linea al momento de
            // vender. El descuento del total no se prorratea por linea: se resta
            // entero, que es como efectivamente se pierde.
            $grossMargin = (float) SaleItem::query()
                ->whereHas('sale', fn ($q) => $q->where('estado', 'confirmada')
                    ->whereBetween('created_at', [$this->start, $this->end]))
                ->selectRaw('SUM((precio_unitario - COALESCE(costo_unitario, 0)) * cantidad) as margen')
                ->value('margen');

            $discounts = (float) $this->sales()->sum('descuento_monto');

            return [
                'total_inquiries' => $totalInquiries,
                'sold' => $sold,
                'pending' => $pending,
                'closed' => $closed,
                'conversion_rate' => $totalInquiries > 0
                    ? round(($sold / $totalInquiries) * 100, 1)
                    : 0.0,
                'revenue' => $revenue,
                'sales_count' => $salesCount,
                'average_ticket' => $salesCount > 0 ? round($revenue / $salesCount, 2) : 0.0,
                'margin' => $grossMargin - $discounts,
            ];
        });
    }

    /**
     * Serie diaria del rango completo: las consultas salen de `consultas` y los
     * ingresos de `ventas`. Son dos consultas distintas porque miden cosas
     * distintas, y el rango se recorre dia por dia para no saltear los dias sin
     * movimiento (un hueco en la serie se lee como un error del grafico).
     *
     * @return array<int, array{day: string, total: int, sold: int, sales: int, revenue: float}>
     */
    public function series(): array
    {
        return $this->once('series', function () {
            $inquiries = $this->inquiries()
                ->select(
                    DB::raw('DATE(created_at) as day'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as vendidas"),
                )
                ->groupBy('day')
                ->get()
                ->keyBy('day');

            $sales = $this->sales()
                ->select(
                    DB::raw('DATE(created_at) as day'),
                    DB::raw('COUNT(*) as ventas'),
                    DB::raw('SUM(total) as ingresos'),
                )
                ->groupBy('day')
                ->get()
                ->keyBy('day');

            $rows = [];
            $cursor = $this->start->startOfDay();

            while ($cursor->lessThanOrEqualTo($this->end)) {
                $day = $cursor->format('Y-m-d');

                $rows[] = [
                    'day' => $day,
                    'total' => (int) ($inquiries[$day]->total ?? 0),
                    'sold' => (int) ($inquiries[$day]->vendidas ?? 0),
                    'sales' => (int) ($sales[$day]->ventas ?? 0),
                    'revenue' => (float) ($sales[$day]->ingresos ?? 0),
                ];

                $cursor = $cursor->addDay();
            }

            return $rows;
        });
    }

    /**
     * Productos efectivamente vendidos. Convive con "mas solicitados" (que sale
     * de las consultas): interes y venta concretada no son lo mismo.
     *
     * @return array<int, array{product_id: int|null, name: string, code: string, qty: int, revenue: float}>
     */
    public function topSold(int $limit = 10): array
    {
        return $this->once('topSold.'.$limit, fn () => SaleItem::query()
            ->select(
                'producto_id',
                'producto_nombre_copia',
                'producto_codigo_copia',
                DB::raw('SUM(cantidad) as qty'),
                DB::raw('SUM(cantidad * precio_unitario) as revenue'),
            )
            ->whereHas('sale', fn ($q) => $q->where('estado', 'confirmada')
                ->whereBetween('created_at', [$this->start, $this->end]))
            ->groupBy('producto_id', 'producto_nombre_copia', 'producto_codigo_copia')
            ->orderByDesc('qty')
            ->take($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->producto_id,
                'name' => (string) $row->producto_nombre_copia,
                'code' => (string) ($row->producto_codigo_copia ?? ''),
                'qty' => (int) $row->qty,
                'revenue' => (float) $row->revenue,
            ])
            ->all()
        );
    }

    /**
     * Productos mas solicitados en consultas (por cantidad pedida).
     *
     * @return array<int, array{product_id: int|null, name: string, code: string, qty: int, revenue: float}>
     */
    public function topRequested(int $limit = 10): array
    {
        return $this->once('topRequested.'.$limit, fn () => InquiryItem::query()
            ->select(
                'producto_id',
                'producto_nombre_copia',
                'producto_codigo_copia',
                DB::raw('SUM(cantidad) as qty'),
                DB::raw('SUM(cantidad * precio_unitario) as revenue'),
            )
            ->whereHas('inquiry', fn ($q) => $q->whereBetween('created_at', [$this->start, $this->end]))
            ->groupBy('producto_id', 'producto_nombre_copia', 'producto_codigo_copia')
            ->orderByDesc('qty')
            ->take($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->producto_id,
                'name' => (string) $row->producto_nombre_copia,
                'code' => (string) ($row->producto_codigo_copia ?? ''),
                'qty' => (int) $row->qty,
                'revenue' => (float) $row->revenue,
            ])
            ->all()
        );
    }

    /**
     * Productos mas vistos en el catalogo publico.
     *
     * @return array<int, array{product_id: int, name: string, code: string, views: int}>
     */
    public function topViewed(int $limit = 10): array
    {
        return $this->once('topViewed.'.$limit, function () use ($limit) {
            $views = ProductView::query()
                ->select('producto_id', DB::raw('COUNT(*) as views'))
                ->whereBetween('visto_en', [$this->start, $this->end])
                ->groupBy('producto_id')
                ->orderByDesc('views')
                ->take($limit)
                ->get();

            // Los nombres se traen en una sola consulta, no uno por fila.
            $products = Product::query()
                ->whereIn('id', $views->pluck('producto_id'))
                ->get(['id', 'nombre', 'codigo'])
                ->keyBy('id');

            return $views->map(fn ($row) => [
                'product_id' => (int) $row->producto_id,
                'name' => $products[$row->producto_id]->nombre ?? '—',
                'code' => (string) ($products[$row->producto_id]->codigo ?? ''),
                'views' => (int) $row->views,
            ])->all();
        });
    }

    /**
     * Consultas por estado del embudo. Se recorren los estados declarados en el
     * modelo para que un estado sin consultas aparezca en cero en vez de
     * desaparecer del grafico.
     *
     * @return array<int, array{status: string, total: int}>
     */
    public function statusBreakdown(): array
    {
        return $this->once('statusBreakdown', function () {
            // El `get()` antes del `pluck()` no sobra: `pluck()` sobre el query
            // reemplaza las columnas del `select`, y con el agregado fuera la
            // consulta pide una columna `total` que no existe.
            $counts = $this->inquiries()
                ->select('estado', DB::raw('COUNT(*) as total'))
                ->groupBy('estado')
                ->get()
                ->pluck('total', 'estado');

            return collect(Inquiry::STATUSES)
                ->map(fn (string $status) => [
                    'status' => $status,
                    'total' => (int) ($counts[$status] ?? 0),
                ])
                ->all();
        });
    }

    /**
     * Ingresos por categoria de producto: agregado con join sobre las lineas de
     * venta. Las lineas cuyo producto fue borrado quedan bajo "Sin categoria"
     * en vez de perderse, para que la suma del grafico cuadre con los ingresos
     * del rango.
     *
     * @return array<int, array{category: string, units: int, revenue: float}>
     */
    public function revenueByCategory(int $limit = 8): array
    {
        return $this->once('revenueByCategory.'.$limit, fn () => DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->leftJoin('productos as p', 'p.id', '=', 'vi.producto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->where('v.estado', 'confirmada')
            ->whereBetween('v.created_at', [$this->start, $this->end])
            ->selectRaw("COALESCE(c.nombre, 'Sin categoria') as categoria")
            ->selectRaw('SUM(vi.cantidad) as unidades')
            ->selectRaw('SUM(vi.cantidad * vi.precio_unitario) as ingresos')
            ->groupBy('categoria')
            ->orderByDesc('ingresos')
            ->take($limit)
            ->get()
            ->map(fn ($row) => [
                'category' => (string) $row->categoria,
                'units' => (int) $row->unidades,
                'revenue' => (float) $row->ingresos,
            ])
            ->all()
        );
    }

    /**
     * Totales de la serie diaria, para la fila de cierre del PDF y del Excel.
     *
     * @return array{inquiries: int, sold: int, sales: int, revenue: float}
     */
    public function seriesTotals(): array
    {
        $series = collect($this->series());

        return [
            'inquiries' => (int) $series->sum('total'),
            'sold' => (int) $series->sum('sold'),
            'sales' => (int) $series->sum('sales'),
            'revenue' => (float) $series->sum('revenue'),
        ];
    }
}

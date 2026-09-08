<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\CsvDownload;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $end = $request->date('to') ?? CarbonImmutable::now()->endOfDay();
        $start = $request->date('from') ?? $end->subDays(29)->startOfDay();

        $inquiriesInRange = Inquiry::query()
            ->whereBetween('created_at', [$start, $end]);

        $totalInquiries = (clone $inquiriesInRange)->count();
        $soldInquiries = (clone $inquiriesInRange)->where('estado', 'vendido')->count();
        $pendingInquiries = (clone $inquiriesInRange)->where('estado', 'pendiente')->count();
        $closedInquiries = (clone $inquiriesInRange)->where('estado', 'cerrado')->count();

        $conversionRate = $totalInquiries > 0
            ? round(($soldInquiries / $totalInquiries) * 100, 1)
            : 0.0;

        // Los ingresos salen de las ventas confirmadas, no de las consultas: el
        // total estimado de una consulta es lo que el cliente pidió, no lo que
        // se cobró (no contempla descuento en mostrador ni cambio de precio).
        $salesInRange = Sale::query()
            ->where('estado', 'confirmada')
            ->whereBetween('created_at', [$start, $end]);

        $revenue = (float) (clone $salesInRange)->sum('total');
        $salesCount = (clone $salesInRange)->count();
        $averageTicket = $salesCount > 0 ? round($revenue / $salesCount, 2) : 0.0;

        // Margen bruto con el costo congelado en cada línea al momento de
        // vender. El descuento del total no se prorratea por línea: se resta
        // entero, que es como efectivamente se pierde.
        $grossMargin = (float) SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->where('estado', 'confirmada')
                ->whereBetween('created_at', [$start, $end]))
            ->selectRaw('SUM((precio_unitario - COALESCE(costo_unitario, 0)) * cantidad) as margen')
            ->value('margen');

        $discounts = (float) (clone $salesInRange)->sum('descuento_monto');
        $margin = $grossMargin - $discounts;

        // Serie diaria: las consultas salen de `consultas`, los ingresos de
        // `ventas`. Son dos consultas distintas porque miden cosas distintas.
        $dailyInquiries = (clone $inquiriesInRange)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $dailyRevenue = (clone $salesInRange)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('SUM(total) as revenue'))
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = [];
        $cursor = $start->startOfDay();
        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m-d');
            $series[] = [
                'day' => $key,
                'total' => (int) ($dailyInquiries[$key]->total ?? 0),
                'revenue' => (float) ($dailyRevenue[$key]->revenue ?? 0),
            ];
            $cursor = $cursor->addDay();
        }

        // Top productos efectivamente vendidos. Convive con "más solicitados"
        // (que sale de las consultas): interés y venta concretada no son lo
        // mismo, y perder el primero sería una regresión.
        $topSold = SaleItem::query()
            ->select(
                'producto_id as product_id',
                'producto_nombre_copia as name',
                'producto_codigo_copia as code',
                DB::raw('SUM(cantidad) as qty'),
                DB::raw('SUM(cantidad * precio_unitario) as revenue'),
            )
            ->whereHas('sale', fn ($q) => $q->where('estado', 'confirmada')
                ->whereBetween('created_at', [$start, $end]))
            ->groupBy('producto_id', 'producto_nombre_copia', 'producto_codigo_copia')
            ->orderByDesc('qty')
            ->take(10)
            ->get();

        // Top productos solicitados (por cantidad en items)
        $topProducts = InquiryItem::query()
            ->select(
                'producto_id as product_id',
                'producto_nombre_copia as name',
                'producto_codigo_copia as code',
                DB::raw('SUM(cantidad) as qty'),
                DB::raw('SUM(cantidad * precio_unitario) as revenue'),
            )
            ->whereHas('inquiry', fn ($q) => $q->whereBetween('created_at', [$start, $end]))
            ->groupBy('producto_id', 'producto_nombre_copia', 'producto_codigo_copia')
            ->orderByDesc('qty')
            ->take(10)
            ->get();

        // Productos más vistos
        $topViewed = ProductView::query()
            ->select('producto_id', DB::raw('COUNT(*) as views'))
            ->whereBetween('visto_en', [$start, $end])
            ->groupBy('producto_id')
            ->orderByDesc('views')
            ->take(10)
            ->get();

        $topViewedHydrated = Product::query()
            ->whereIn('id', $topViewed->pluck('producto_id'))
            ->get(['id', 'nombre', 'codigo'])
            ->keyBy('id');

        $topViewedFinal = $topViewed->map(fn ($v) => [
            'product_id' => $v->product_id,
            'views' => (int) $v->views,
            'name' => $topViewedHydrated[$v->product_id]->name ?? '—',
            'code' => $topViewedHydrated[$v->product_id]->code ?? '',
        ]);

        return Inertia::render('admin/reports/Index', [
            'range' => [
                'from' => $start->format('Y-m-d'),
                'to' => $end->format('Y-m-d'),
            ],
            'kpis' => [
                'total_inquiries' => $totalInquiries,
                'sold' => $soldInquiries,
                'pending' => $pendingInquiries,
                'closed' => $closedInquiries,
                'conversion_rate' => $conversionRate,
                'revenue' => $revenue,
                'sales_count' => $salesCount,
                'average_ticket' => $averageTicket,
                'margin' => $margin,
            ],
            'series' => $series,
            'topProducts' => $topProducts,
            'topSold' => $topSold,
            'topViewed' => $topViewedFinal,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $end = $request->date('to') ?? CarbonImmutable::now()->endOfDay();
        $start = $request->date('from') ?? $end->subDays(29)->startOfDay();

        $rows = function () use ($start, $end) {
            // Serie diaria: consultas de `consultas`, ingresos reales de `ventas`.
            $daily = Inquiry::query()
                ->whereBetween('created_at', [$start, $end])
                ->select(
                    DB::raw('DATE(created_at) as day'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as vendidas"),
                )
                ->groupBy('day')
                ->get()
                ->keyBy('day');

            $sales = Sale::query()
                ->where('estado', 'confirmada')
                ->whereBetween('created_at', [$start, $end])
                ->select(
                    DB::raw('DATE(created_at) as day'),
                    DB::raw('COUNT(*) as ventas'),
                    DB::raw('SUM(total) as ingresos'),
                )
                ->groupBy('day')
                ->get()
                ->keyBy('day');

            // Se recorre el rango completo para no saltear días sin datos en
            // una de las dos tablas.
            $cursor = $start->startOfDay();

            while ($cursor->lessThanOrEqualTo($end)) {
                $day = $cursor->format('Y-m-d');

                yield [
                    $day,
                    (int) ($daily[$day]->total ?? 0),
                    (int) ($daily[$day]->vendidas ?? 0),
                    (int) ($sales[$day]->ventas ?? 0),
                    number_format((float) ($sales[$day]->ingresos ?? 0), 2, '.', ''),
                ];

                $cursor = $cursor->addDay();
            }
        };

        return CsvDownload::stream(
            'reporte-'.$start->format('Y-m-d').'_'.$end->format('Y-m-d').'.csv',
            ['Fecha', 'Consultas', 'Consultas vendidas', 'Ventas', 'Ingresos'],
            $rows(),
        );
    }
}

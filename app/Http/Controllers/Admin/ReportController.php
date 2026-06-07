<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use App\Models\ProductView;
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

        $estimatedRevenue = (float) (clone $inquiriesInRange)
            ->where('estado', 'vendido')
            ->sum('total_estimado');

        $conversionRate = $totalInquiries > 0
            ? round(($soldInquiries / $totalInquiries) * 100, 1)
            : 0.0;

        // Serie diaria de consultas e ingresos
        $dailyRaw = (clone $inquiriesInRange)
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN estado = 'vendido' THEN total_estimado ELSE 0 END) as revenue"),
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $series = [];
        $cursor = $start->startOfDay();
        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m-d');
            $series[] = [
                'day' => $key,
                'total' => (int) ($dailyRaw[$key]->total ?? 0),
                'revenue' => (float) ($dailyRaw[$key]->revenue ?? 0),
            ];
            $cursor = $cursor->addDay();
        }

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
                'estimated_revenue' => $estimatedRevenue,
                'conversion_rate' => $conversionRate,
            ],
            'series' => $series,
            'topProducts' => $topProducts,
            'topViewed' => $topViewedFinal,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $end = $request->date('to') ?? CarbonImmutable::now()->endOfDay();
        $start = $request->date('from') ?? $end->subDays(29)->startOfDay();

        $rows = function () use ($start, $end) {
            // Serie diaria de consultas e ingresos
            $daily = Inquiry::query()
                ->whereBetween('created_at', [$start, $end])
                ->select(
                    DB::raw('DATE(created_at) as day'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as vendidas"),
                    DB::raw("SUM(CASE WHEN estado = 'vendido' THEN total_estimado ELSE 0 END) as ingresos"),
                )
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            foreach ($daily as $d) {
                yield [
                    $d->day,
                    (int) $d->total,
                    (int) $d->vendidas,
                    number_format((float) $d->ingresos, 2, '.', ''),
                ];
            }
        };

        return CsvDownload::stream(
            'reporte-'.$start->format('Y-m-d').'_'.$end->format('Y-m-d').'.csv',
            ['Fecha', 'Consultas', 'Vendidas', 'Ingresos estimados'],
            $rows(),
        );
    }
}

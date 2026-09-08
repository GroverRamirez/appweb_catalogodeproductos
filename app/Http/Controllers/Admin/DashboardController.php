<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** TTL en segundos para datos estadísticos (no operacionales). */
    private const CACHE_TTL = 300; // 5 minutos

    /**
     * Sufijo de versión — incrementar cuando cambie la forma del dato cacheado
     * para invalidar automáticamente entradas stale sin necesitar cache:clear.
     */
    private const V = 'v4';

    public function index(): Response
    {
        // ── Datos con cache (cambian poco, costosos de recalcular) ──────────────

        /** @var array<string, int|float> */
        $stats = Cache::remember('dashboard.stats.'.self::V, self::CACHE_TTL, function () {
            // Una sola pasada por `productos` para todos los agregados de catálogo:
            // evita 6 COUNT(*) separados sobre la misma tabla.
            $products = Product::query()
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(activo = 1) as active')
                ->selectRaw('SUM(stock <= stock_minimo) as low_stock')
                ->selectRaw('SUM(stock = 0) as out_of_stock')
                ->selectRaw('SUM(costo IS NULL OR costo <= 0) as without_cost')
                ->selectRaw('SUM(stock * COALESCE(costo, 0)) as inventory_cost')
                ->selectRaw('SUM(stock * COALESCE(precio_oferta, precio)) as inventory_retail')
                ->first();

            return [
                'products_total' => (int) $products->total,
                'products_active' => (int) $products->active,
                'products_low_stock' => (int) $products->low_stock,
                'products_out_of_stock' => (int) $products->out_of_stock,
                'products_without_cost' => (int) $products->without_cost,
                'inventory_cost' => (float) $products->inventory_cost,
                'inventory_retail' => (float) $products->inventory_retail,
                'inquiries_pending' => Inquiry::pending()->count(),
                'inquiries_total' => Inquiry::count(),
                'purchases_spend_30d' => (float) Purchase::query()
                    ->where('estado', 'confirmada')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->sum('costo_total'),
                'purchases_count_30d' => Purchase::query()
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
                'sales_revenue_30d' => (float) Sale::query()
                    ->where('estado', 'confirmada')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->sum('total'),
                'sales_count_30d' => Sale::query()
                    ->where('estado', 'confirmada')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
            ];
        });

        $topProducts = Cache::remember('dashboard.top_products.'.self::V, self::CACHE_TTL, fn () => Product::query()
            ->select('id', 'nombre', 'codigo', 'visitas', 'stock')
            ->orderByDesc('visitas')
            ->take(5)
            ->get()
            ->toArray()
        );

        $lowStockProducts = Cache::remember('dashboard.low_stock.'.self::V, self::CACHE_TTL, fn () => Product::query()
            ->select('id', 'nombre', 'codigo', 'stock', 'stock_minimo')
            ->lowStock()
            ->orderBy('stock')
            ->take(5)
            ->get()
            ->toArray()
        );

        // La clave incluye la fecha para que expire naturalmente a medianoche.
        // La versión garantiza que entradas previas (formato antiguo) se ignoran.
        $today = now()->format('Y-m-d');
        $viewsLast7Days = Cache::remember("dashboard.views_7d.{$today}.".self::V, self::CACHE_TTL,
            fn () => DB::table('producto_visitas')
                ->selectRaw('DATE(visto_en) as day, COUNT(*) as total')
                ->where('visto_en', '>=', now()->subDays(7))
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($row) => ['day' => $row->day, 'total' => (int) $row->total])
                ->values()
                ->all()
        );

        // Gasto en compras confirmadas de los últimos 6 meses, para el gráfico.
        // El truncado a mes difiere por driver: MySQL usa DATE_FORMAT, SQLite
        // (tests) usa strftime.
        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $spendByMonth = Cache::remember("dashboard.spend_6m.{$today}.".self::V, self::CACHE_TTL,
            fn () => Purchase::query()
                ->selectRaw("{$monthExpr} as month, SUM(costo_total) as total")
                ->where('estado', 'confirmada')
                ->where('created_at', '>=', now()->startOfMonth()->subMonths(5))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($row) => ['month' => $row->month, 'total' => (float) $row->total])
                ->values()
                ->all()
        );

        // ── Datos sin cache (operacionales, el admin necesita verlos en tiempo real)
        $recentInquiries = Inquiry::query()
            ->select('id', 'cliente_nombre', 'cliente_telefono', 'estado', 'origen', 'created_at')
            ->latest()
            ->take(5)
            ->get();

        $recentPurchases = Purchase::query()
            ->with('supplier:id,nombre')
            ->withCount('items')
            ->latest()
            ->take(5)
            ->get(['id', 'proveedor_id', 'numero_referencia', 'estado', 'costo_total', 'created_at']);

        return Inertia::render('admin/Dashboard', [
            'stats' => $stats,
            'topProducts' => $topProducts,
            'lowStockProducts' => $lowStockProducts,
            'recentInquiries' => $recentInquiries,
            'recentPurchases' => $recentPurchases,
            'viewsLast7Days' => $viewsLast7Days,
            'spendByMonth' => $spendByMonth,
        ]);
    }

    /**
     * Invalida todas las claves de cache del dashboard.
     * Llamar desde model events cuando cambien productos, categorías, marcas,
     * consultas o compras.
     */
    public static function flushCache(): void
    {
        $today = now()->format('Y-m-d');

        Cache::forget('dashboard.stats.'.self::V);
        Cache::forget('dashboard.top_products.'.self::V);
        Cache::forget('dashboard.low_stock.'.self::V);
        Cache::forget("dashboard.views_7d.{$today}.".self::V);
        Cache::forget("dashboard.spend_6m.{$today}.".self::V);
    }
}

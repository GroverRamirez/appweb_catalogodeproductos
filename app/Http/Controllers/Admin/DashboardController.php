<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inquiry;
use App\Models\Product;
use App\Models\ProductView;
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
    private const V = 'v2';

    public function index(): Response
    {
        // ── Datos con cache (cambian poco, costosos de recalcular) ──────────────

        /** @var array<string, int> */
        $stats = Cache::remember('dashboard.stats.'.self::V, self::CACHE_TTL, fn () => [
            'products_total'        => Product::count(),
            'products_active'       => Product::active()->count(),
            'products_low_stock'    => Product::lowStock()->count(),
            'products_out_of_stock' => Product::where('stock', 0)->count(),
            'categories_total'      => Category::count(),
            'brands_total'          => Brand::count(),
            'inquiries_pending'     => Inquiry::pending()->count(),
            'inquiries_total'       => Inquiry::count(),
        ]);

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

        // ── Datos sin cache (operacionales, el admin necesita verlos en tiempo real)
        $recentInquiries = Inquiry::query()
            ->select('id', 'cliente_nombre', 'cliente_telefono', 'estado', 'origen', 'created_at')
            ->latest()
            ->take(5)
            ->get();

        return Inertia::render('admin/Dashboard', [
            'stats'            => $stats,
            'topProducts'      => $topProducts,
            'lowStockProducts' => $lowStockProducts,
            'recentInquiries'  => $recentInquiries,
            'viewsLast7Days'   => $viewsLast7Days,
        ]);
    }

    /**
     * Invalida todas las claves de cache del dashboard.
     * Llamar desde model events cuando cambien productos, categorías, marcas o consultas.
     */
    public static function flushCache(): void
    {
        Cache::forget('dashboard.stats.'.self::V);
        Cache::forget('dashboard.top_products.'.self::V);
        Cache::forget('dashboard.low_stock.'.self::V);
        Cache::forget('dashboard.views_7d.'.now()->format('Y-m-d').'.'.self::V);
    }
}

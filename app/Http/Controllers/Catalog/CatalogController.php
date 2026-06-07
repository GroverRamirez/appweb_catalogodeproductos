<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Jobs\RecordProductView;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductView;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    /** Resolve an asset path to an absolute URL (same logic as HandleInertiaRequests). */
    private function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') ? $path : Storage::url($path);
    }

    /** Trim a string to at most $max chars, breaking at word boundary. */
    private function excerpt(?string $text, int $max = 155): ?string
    {
        if (! $text) {
            return null;
        }
        $text = strip_tags($text);

        return mb_strlen($text) <= $max ? $text : mb_substr($text, 0, $max - 1).'…';
    }

    public function home(): Response
    {
        $storeName = Setting::get('store_name', config('app.name'));
        $tagline   = Setting::get('store_tagline', '');
        $logoUrl   = $this->assetUrl(Setting::get('logo_path'));

        return Inertia::render('catalog/Home', [
            'banners' => Banner::query()
                ->active()
                ->orderBy('orden')
                ->orderByDesc('id')
                ->get()
                ->map(fn ($b) => [
                    'id'        => $b->id,
                    'title'     => $b->title,
                    'subtitle'  => $b->subtitle,
                    'image_url' => $b->image_url,
                    'link'      => $b->link,
                    'cta_text'  => $b->cta_text,
                ]),

            'featured' => ProductResource::collection(
                Product::query()
                    ->active()
                    ->featured()
                    ->with(['mainImage', 'category:id,nombre', 'brand:id,nombre'])
                    ->take(8)
                    ->get()
            ),

            'newest' => ProductResource::collection(
                Product::query()
                    ->active()
                    ->with(['mainImage', 'category:id,nombre'])
                    ->latest()
                    ->take(8)
                    ->get()
            ),

            'categories' => Category::cachedHomeRoots(),

            'seo' => [
                'title'       => '',            // empty → title callback returns just appName
                'description' => $tagline ?: null,
                'canonical'   => url('/'),
                'og_image'    => $logoUrl,
            ],
        ]);
    }

    public function index(Request $request): Response
    {
        $perPage   = (int) (Setting::get('products_per_page', 12)) ?: 12;
        $storeName = Setting::get('store_name', config('app.name'));

        $products = Product::query()
            ->active()
            ->with(['mainImage', 'category:id,nombre', 'brand:id,nombre'])
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('category'), function ($q) use ($request) {
                $slug = $request->string('category')->toString();
                $cat  = Category::where('slug', $slug)->first();
                if ($cat) {
                    // incluir subcategorías
                    $ids = Category::where('id', $cat->id)
                        ->orWhere('categoria_padre_id', $cat->id)
                        ->pluck('id');
                    $q->whereIn('categoria_id', $ids);
                }
            })
            ->when($request->filled('brand'), function ($q) use ($request) {
                $slug  = $request->string('brand')->toString();
                $brand = Brand::where('slug', $slug)->first();
                if ($brand) {
                    $q->where('marca_id', $brand->id);
                }
            })
            ->when($request->filled('min_price'), fn ($q) => $q->where('precio', '>=', $request->float('min_price')))
            ->when($request->filled('max_price'), fn ($q) => $q->where('precio', '<=', $request->float('max_price')))
            ->when($request->boolean('in_stock'), fn ($q) => $q->inStock())
            ->when($request->string('sort')->toString() === 'price_asc', fn ($q) => $q->orderBy('precio'))
            ->when($request->string('sort')->toString() === 'price_desc', fn ($q) => $q->orderByDesc('precio'))
            ->when($request->string('sort')->toString() === 'newest', fn ($q) => $q->latest())
            ->when($request->string('sort')->toString() === 'most_viewed', fn ($q) => $q->orderByDesc('visitas'))
            ->when($request->string('sort')->toString() === 'discount', function ($q) {
                $q->whereNotNull('precio_oferta')
                    ->whereColumn('precio_oferta', '<', 'precio')
                    ->orderByRaw('(1 - (precio_oferta / precio)) DESC');
            })
            ->when(! $request->filled('sort'), fn ($q) => $q->orderByDesc('destacado')->latest())
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('catalog/Index', [
            // ProductResource::collection() sobre un LengthAwarePaginator preserva
            // la estructura { data, links, meta } que Inertia y el frontend esperan.
            'products'   => ProductResource::collection($products),
            'filters'    => $request->only(['q', 'category', 'brand', 'min_price', 'max_price', 'in_stock', 'sort']),
            'categories' => Category::cachedAll(),
            'brands'     => Brand::cachedAll(),
            'seo'        => [
                'title'       => 'Catálogo',
                'description' => $this->excerpt("Explora nuestro catálogo de productos de {$storeName}. Filtra por categoría, marca y precio."),
                'canonical'   => url('/catalogo'),
                'og_image'    => $this->assetUrl(Setting::get('logo_path')),
                // noindex filtered/sorted views so only the canonical is indexed
                'noindex'     => $request->hasAny(['q', 'category', 'brand', 'min_price', 'max_price', 'in_stock', 'sort']),
            ],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $product = Product::query()
            ->active()
            ->with(['images', 'attributes', 'category:id,nombre,slug', 'brand:id,nombre,slug'])
            ->where('slug', $slug)
            ->firstOrFail();

        // Registrar vista de forma asíncrona (no bloquea el response).
        // La deduplicación se hace aquí de forma síncrona: escribimos la clave de
        // sesión ANTES del dispatch para que requests rápidos consecutivos no
        // encolen dos jobs para la misma visita.
        $sessionKey = "viewed_product_{$product->id}";
        if (! $request->session()->has($sessionKey)) {
            $request->session()->put($sessionKey, true);

            RecordProductView::dispatch(
                $product->id,
                (string) $request->ip(),
                $request->session()->getId(),
                substr((string) $request->userAgent(), 0, 500),
                substr((string) $request->header('referer'), 0, 500),
            );
        }

        $related     = $this->buildRelated($product, 8);
        $alsoViewed  = $this->buildAlsoViewed($product, 4);

        // SEO — calcular antes de pasar el producto al Resource
        $mainImagePath = $product->images->firstWhere('principal', true)?->path
            ?? $product->images->first()?->path;
        $ogImage = $this->assetUrl($mainImagePath);

        $description = $this->excerpt($product->short_description ?? $product->description);

        $storeName  = Setting::get('store_name', config('app.name'));
        $currency   = Setting::get('currency', 'PEN');
        $showPrices = (bool) Setting::get('show_prices', true);

        $jsonLd = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $product->name,
            'description' => $description,
            'sku'         => $product->code,
            'url'         => url('/catalogo/'.$product->slug),
            'image'       => $ogImage ? [$ogImage] : [],
            'brand'       => $product->brand
                ? ['@type' => 'Brand', 'name' => $product->brand->name]
                : null,
            'offers'      => $showPrices ? [
                '@type'         => 'Offer',
                'priceCurrency' => $currency,
                'price'         => $product->sale_price ?? $product->price,
                'availability'  => $product->stock > 0
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'seller'        => ['@type' => 'Organization', 'name' => $storeName],
            ] : null,
        ];

        return Inertia::render('catalog/Show', [
            'product'    => new ProductResource($product),
            'related'    => ProductResource::collection($related),
            'alsoViewed' => ProductResource::collection($alsoViewed),
            'seo'        => [
                'title'       => $product->name,
                'description' => $description,
                'canonical'   => url('/catalogo/'.$product->slug),
                'og_image'    => $ogImage,
                'og_type'     => 'product',
                'json_ld'     => $jsonLd,
            ],
        ]);
    }

    /**
     * Algoritmo de relacionados:
     *  1. Productos de la misma categoría Y misma marca (mejor match)
     *  2. Completar con productos de la misma categoría
     *  3. Si todavía falta, completar con la misma marca
     */
    protected function buildRelated(Product $product, int $count = 8): Collection
    {
        $base = Product::query()
            ->active()
            ->where('id', '!=', $product->id)
            ->with(['mainImage', 'category:id,nombre', 'brand:id,nombre']);

        $collected = collect();

        if ($product->category_id && $product->brand_id) {
            $collected = $collected->concat(
                (clone $base)
                    ->where('categoria_id', $product->category_id)
                    ->where('marca_id', $product->brand_id)
                    ->inRandomOrder()
                    ->take($count)
                    ->get()
            );
        }

        if ($collected->count() < $count && $product->category_id) {
            $needed    = $count - $collected->count();
            $collected = $collected->concat(
                (clone $base)
                    ->where('categoria_id', $product->category_id)
                    ->whereNotIn('id', $collected->pluck('id'))
                    ->inRandomOrder()
                    ->take($needed)
                    ->get()
            );
        }

        if ($collected->count() < $count && $product->brand_id) {
            $needed    = $count - $collected->count();
            $collected = $collected->concat(
                (clone $base)
                    ->where('marca_id', $product->brand_id)
                    ->whereNotIn('id', $collected->pluck('id'))
                    ->inRandomOrder()
                    ->take($needed)
                    ->get()
            );
        }

        return $collected->take($count)->values();
    }

    /**
     * "También te puede interesar": productos más vistos en la misma categoría
     * (últimos 30 días), excluyendo el actual.
     */
    protected function buildAlsoViewed(Product $product, int $count = 4): Collection
    {
        if (! $product->category_id) {
            return collect();
        }

        $popularIds = ProductView::query()
            ->select('producto_id', DB::raw('COUNT(*) as views'))
            ->where('visto_en', '>=', now()->subDays(30))
            ->whereHas('product', fn ($q) => $q
                ->where('categoria_id', $product->category_id)
                ->where('id', '!=', $product->id)
            )
            ->groupBy('producto_id')
            ->orderByDesc('views')
            ->take($count)
            ->pluck('producto_id');

        if ($popularIds->isEmpty()) {
            // Fallback: por views_count total
            return Product::query()
                ->active()
                ->where('id', '!=', $product->id)
                ->where('categoria_id', $product->category_id)
                ->with(['mainImage', 'category:id,nombre', 'brand:id,nombre'])
                ->orderByDesc('visitas')
                ->take($count)
                ->get();
        }

        return Product::query()
            ->active()
            ->whereIn('id', $popularIds)
            ->with(['mainImage', 'category:id,nombre', 'brand:id,nombre'])
            ->get()
            ->sortBy(fn ($p) => $popularIds->search($p->id))
            ->values();
    }
}

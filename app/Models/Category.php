<?php

namespace App\Models;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Concerns\HasSpanishAliases;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasSpanishAliases, SoftDeletes;

    protected $table = 'categorias';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'image',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    protected function aliases(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripcion',
            'parent_id' => 'categoria_padre_id',
            'image' => 'imagen',
            'sort_order' => 'orden',
            'is_active' => 'activo',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if (empty($category->slug) && ! empty($category->nombre)) {
                $category->slug = static::uniqueSlug($category->nombre, $category->id);
            }
        });

        $flush = fn () => static::flushCache();
        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);

        // Invalidar también el dashboard cuando cambia el total de categorías.
        static::saved(fn () => DashboardController::flushCache());
        static::deleted(fn () => DashboardController::flushCache());
    }

    // Cache key version — bump this suffix if the stored shape changes.
    private const CACHE_HOME = 'catalog.categories.home.v3';

    private const CACHE_ALL = 'catalog.categories.all.v2';

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_HOME);
        Cache::forget(self::CACHE_ALL);
    }

    /**
     * Top-level active categories for the homepage grid (≤6, ordered by sort_order).
     * Stores a plain PHP array — Eloquent objects must never be cached because
     * PHP file-cache serialization returns __PHP_Incomplete_Class on read.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cachedHomeRoots(): array
    {
        $data = Cache::remember(self::CACHE_HOME, now()->addHours(6), fn () => static::buildHomeRoots());

        // Guard: if a stale non-array entry somehow slipped in, regenerate.
        if (! is_array($data)) {
            Cache::forget(self::CACHE_HOME);
            $data = static::buildHomeRoots();
            Cache::put(self::CACHE_HOME, $data, now()->addHours(6));
        }

        return $data;
    }

    /**
     * Arma las tarjetas de categoría de la portada.
     *
     * Cada tarjeta viaja con una imagen y con cuántos productos tiene. Manda la
     * imagen propia de la categoría; si no hay, se usa la foto de uno de sus
     * productos, porque una vitrina de categorías sin fotos no invita a entrar.
     * Las subcategorías suman a su raíz: es lo mismo que hace el filtro del
     * catálogo cuando alguien hace clic en la tarjeta.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function buildHomeRoots(): array
    {
        $roots = static::query()
            ->whereNull('categoria_padre_id')
            ->active()
            ->orderBy('orden')
            ->take(6)
            ->get(['id', 'nombre', 'slug', 'imagen'])
            ->toArray();

        if ($roots === []) {
            return [];
        }

        $rootIds = array_column($roots, 'id');

        // id de subcategoría => id de su raíz
        $childToRoot = static::query()
            ->whereIn('categoria_padre_id', $rootIds)
            ->active()
            ->pluck('categoria_padre_id', 'id')
            ->all();

        $familyIds = array_merge($rootIds, array_keys($childToRoot));
        $rootOf = fn (int $categoryId) => (int) ($childToRoot[$categoryId] ?? $categoryId);

        $counts = [];

        foreach (DB::table('productos')
            ->selectRaw('categoria_id, COUNT(*) as total')
            ->whereIn('categoria_id', $familyIds)
            ->where('activo', true)
            ->whereNull('deleted_at')
            ->groupBy('categoria_id')
            ->get() as $row) {
            $root = $rootOf((int) $row->categoria_id);
            $counts[$root] = ($counts[$root] ?? 0) + (int) $row->total;
        }

        // Una sola pasada por las fotos: gana la principal del producto más
        // visto, y la primera que llega para cada raíz se queda.
        $images = [];

        foreach (DB::table('producto_imagenes as pi')
            ->join('productos as p', 'p.id', '=', 'pi.producto_id')
            ->whereIn('p.categoria_id', $familyIds)
            ->where('p.activo', true)
            ->whereNull('p.deleted_at')
            ->orderByDesc('pi.principal')
            ->orderByDesc('p.visitas')
            ->get(['p.categoria_id', 'pi.ruta']) as $row) {
            $root = $rootOf((int) $row->categoria_id);
            $images[$root] ??= $row->ruta;
        }

        return array_map(fn (array $root) => $root + [
            'product_image' => $images[$root['id']] ?? null,
            'products_count' => $counts[$root['id']] ?? 0,
        ], $roots);
    }

    /**
     * All active categories for the catalog sidebar filters.
     * Stores a plain PHP array — same reason as cachedHomeRoots().
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cachedAll(): array
    {
        $data = Cache::remember(self::CACHE_ALL, now()->addHours(6), function () {
            return static::query()
                ->active()
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'slug', 'categoria_padre_id'])
                ->toArray();
        });

        if (! is_array($data)) {
            Cache::forget(self::CACHE_ALL);
            $data = static::query()
                ->active()
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'slug', 'categoria_padre_id'])
                ->toArray();
            Cache::put(self::CACHE_ALL, $data, now()->addHours(6));
        }

        return $data;
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'categoria_padre_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'categoria_padre_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'categoria_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('categoria_padre_id');
    }
}

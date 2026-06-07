<?php

namespace App\Models;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Concerns\HasSpanishAliases;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory, HasSpanishAliases, SoftDeletes;

    protected $table = 'marcas';

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'website',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected function aliases(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripcion',
            'website' => 'sitio_web',
            'is_active' => 'activo',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Brand $brand) {
            if (empty($brand->slug) && ! empty($brand->nombre)) {
                $brand->slug = static::uniqueSlug($brand->nombre, $brand->id);
            }
        });

        $flush = fn () => static::flushCache();
        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);

        // Invalidar también el dashboard cuando cambia el total de marcas.
        static::saved(fn () => DashboardController::flushCache());
        static::deleted(fn () => DashboardController::flushCache());
    }

    // Cache key version — bump if the stored shape changes.
    private const CACHE_ALL = 'catalog.brands.all.v2';

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_ALL);
    }

    /**
     * All active brands for the catalog sidebar filters.
     * Stored as a plain PHP array — Eloquent objects must never be cached because
     * PHP file-cache serialization returns __PHP_Incomplete_Class on read.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cachedAll(): array
    {
        $data = Cache::remember(self::CACHE_ALL, now()->addHours(6), function () {
            return static::query()
                ->active()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'slug'])
                ->toArray();
        });

        // Guard: regenerate if a stale non-array entry was deserialized.
        if (! is_array($data)) {
            Cache::forget(self::CACHE_ALL);
            $data = static::query()
                ->active()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'slug'])
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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'marca_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}

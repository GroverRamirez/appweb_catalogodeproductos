<?php

namespace App\Models;

use App\Http\Controllers\Admin\DashboardController;
use App\Models\Concerns\HasSpanishAliases;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasSpanishAliases, SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'code',
        'name',
        'slug',
        'short_description',
        'description',
        'price',
        'sale_price',
        'cost',
        'stock',
        'min_stock',
        'unit',
        'category_id',
        'brand_id',
        'is_featured',
        'is_active',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'precio_oferta' => 'decimal:2',
            'costo' => 'decimal:2',
            'stock' => 'integer',
            'stock_minimo' => 'integer',
            'visitas' => 'integer',
            'destacado' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    protected function aliases(): array
    {
        return [
            'code' => 'codigo',
            'name' => 'nombre',
            'short_description' => 'descripcion_corta',
            'description' => 'descripcion',
            'price' => 'precio',
            'sale_price' => 'precio_oferta',
            'cost' => 'costo',
            'min_stock' => 'stock_minimo',
            'unit' => 'unidad',
            'category_id' => 'categoria_id',
            'brand_id' => 'marca_id',
            'is_featured' => 'destacado',
            'is_active' => 'activo',
            'views_count' => 'visitas',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if (empty($product->slug) && ! empty($product->nombre)) {
                $product->slug = static::uniqueSlug($product->nombre, $product->id);
            }
        });

        // Invalidar cache del dashboard cuando cambia el inventario o conteos.
        static::saved(fn () => DashboardController::flushCache());
        static::deleted(fn () => DashboardController::flushCache());
        static::restored(fn () => DashboardController::flushCache());
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

    // Relaciones
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'categoria_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class, 'marca_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'producto_id')->orderBy('orden');
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class, 'producto_id')->where('principal', true);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class, 'producto_id')->orderBy('orden');
    }

    public function views(): HasMany
    {
        return $this->hasMany(ProductView::class, 'producto_id');
    }

    // Accessors
    public function getCurrentPriceAttribute(): float
    {
        return (float) ($this->sale_price ?: $this->price);
    }

    public function getIsOnSaleAttribute(): bool
    {
        return $this->sale_price !== null && (float) $this->sale_price < (float) $this->price;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->stock <= $this->min_stock;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->stock <= 0;
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('destacado', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'stock_minimo');
    }

    /**
     * Productos sin costo cargado. Sin este dato no se puede valorizar el
     * inventario ni calcular margen, así que el panel los reporta aparte.
     */
    public function scopeWithoutCost(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('costo')->orWhere('costo', '<=', 0));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $driver = $query->getConnection()->getDriverName();

        // MySQL / MariaDB: usar el índice FULLTEXT creado en la migración.
        // El operador IN BOOLEAN MODE permite búsquedas parciales con el prefijo '*'
        // y maneja tildes/caracteres especiales sin lanzar errores de sintaxis.
        // El código (SKU) se busca siempre con LIKE porque es un identificador exacto
        // sin beneficio de fulltext, y porque puede contener guiones/barras que
        // el parser fulltext interpreta como operadores.
        if ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL requiere palabras de al menos 3 chars (innodb_ft_min_token_size).
            // Términos cortos caerían en el fallback LIKE para no devolver 0 resultados.
            $words = array_filter(array_map('trim', explode(' ', $term)));
            $allShort = collect($words)->every(fn ($w) => mb_strlen($w) < 3);

            if ($allShort) {
                return $query->where(function (Builder $q) use ($term) {
                    $like = '%'.$term.'%';
                    $q->where('nombre', 'like', $like)
                        ->orWhere('codigo', 'like', $like)
                        ->orWhere('descripcion_corta', 'like', $like);
                });
            }

            // Construir expresión BOOLEAN MODE: cada palabra se convierte en +word*
            // (must-include, prefix match). Palabras cortas se omiten del fulltext
            // y se manejan con el LIKE de código a continuación.
            $ftWords = collect($words)
                ->filter(fn ($w) => mb_strlen($w) >= 3)
                ->map(fn ($w) => '+'.preg_replace('/[+\-><()*~"@]/', '', $w).'*')
                ->implode(' ');

            return $query->where(function (Builder $q) use ($term, $ftWords) {
                $q->whereRaw(
                    'MATCH(nombre, descripcion_corta, descripcion) AGAINST(? IN BOOLEAN MODE)',
                    [$ftWords]
                )->orWhere('codigo', 'like', '%'.$term.'%');
            });
        }

        // SQLite (tests) y otros drivers: LIKE como fallback.
        return $query->where(function (Builder $q) use ($term) {
            $like = '%'.$term.'%';
            $q->where('nombre', 'like', $like)
                ->orWhere('codigo', 'like', $like)
                ->orWhere('descripcion_corta', 'like', $like);
        });
    }
}

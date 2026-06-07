<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory, HasSpanishAliases;

    protected $table = 'producto_imagenes';

    protected $fillable = [
        'product_id',
        'path',
        'thumb_path',
        'alt',
        'sort_order',
        'is_main',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'boolean',
            'orden' => 'integer',
        ];
    }

    protected function aliases(): array
    {
        return [
            'product_id' => 'producto_id',
            'path' => 'ruta',
            'thumb_path' => 'ruta_thumb',
            'alt' => 'texto_alternativo',
            'sort_order' => 'orden',
            'is_main' => 'principal',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'producto_id');
    }

    /**
     * URL completa de la imagen principal (1200×1200 max).
     */
    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return str_starts_with($this->path, 'http')
            ? $this->path
            : Storage::url($this->path);
    }

    /**
     * URL del thumbnail WebP (400×400 cover crop).
     * Si no existe ruta_thumb (imagen subida antes de la migración), cae al URL principal.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumb_path) {
            return str_starts_with($this->thumb_path, 'http')
                ? $this->thumb_path
                : Storage::url($this->thumb_path);
        }

        // Fallback: imagen sin thumbnail → usar la principal
        return $this->url;
    }
}

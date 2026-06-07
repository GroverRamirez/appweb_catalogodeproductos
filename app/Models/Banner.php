<?php

namespace App\Models;

use App\Models\Concerns\HasSpanishAliases;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory, HasSpanishAliases;

    protected $table = 'banners_publicitarios';

    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'link',
        'cta_text',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
            'inicia_en' => 'datetime',
            'termina_en' => 'datetime',
        ];
    }

    protected function aliases(): array
    {
        return [
            'title' => 'titulo',
            'subtitle' => 'subtitulo',
            'image' => 'imagen',
            'link' => 'enlace',
            'cta_text' => 'texto_cta',
            'sort_order' => 'orden',
            'is_active' => 'activo',
            'starts_at' => 'inicia_en',
            'ends_at' => 'termina_en',
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        return str_starts_with($this->image, 'http')
            ? $this->image
            : Storage::url($this->image);
    }

    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('activo', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('inicia_en')->orWhere('inicia_en', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('termina_en')->orWhere('termina_en', '>=', $now);
            });
    }
}

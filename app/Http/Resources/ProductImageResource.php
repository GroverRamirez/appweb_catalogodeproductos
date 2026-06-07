<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un ProductImage con nombres en inglés, incluyendo las URLs
 * resueltas para la imagen principal y el thumbnail WebP.
 *
 * Campos:
 *   id, path, thumb_path, url, thumb_url, is_main, alt, sort_order
 */
class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'path'       => $this->path,
            'thumb_path' => $this->thumb_path,
            'url'        => $this->url,         // getUrlAttribute()
            'thumb_url'  => $this->thumbnail_url, // getThumbnailUrlAttribute() con fallback
            'is_main'    => (bool) $this->is_main,
            'alt'        => $this->alt,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}

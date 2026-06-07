<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa una Category con nombres en inglés.
 *
 * Campos: id, name, slug, parent_id
 *
 * Nota: `slug` y `parent_id` pueden ser null cuando la relación fue cargada
 * con select parcial (e.g. category:id,nombre).
 */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'slug'      => $this->slug,
            'parent_id' => $this->parent_id,
        ];
    }
}

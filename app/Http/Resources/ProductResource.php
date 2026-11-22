<?php

namespace App\Http\Resources;

use App\Services\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializa un Product para el catálogo público con nombres en inglés,
 * tipos explícitos y relaciones lazy-loaded mediante whenLoaded().
 *
 * Campos siempre presentes:
 *   id, slug, name, code, price, sale_price, stock, unit,
 *   short_description, description, is_featured, is_active,
 *   current_price, is_on_sale, created_at
 *
 * Campos condicionales (solo si la relación fue cargada con with()):
 *   main_image / mainImage — HasOne mainImage()   → usado en Index / Home
 *   images                 — HasMany images()     → usado en Show
 *   category               — BelongsTo category() → ambas vistas
 *   brand                  — BelongsTo brand()    → ambas vistas
 *   attributes             — HasMany attributes() → solo Show
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // La lista de precios se carga en dolares y el cliente compra en
        // bolivianos. La conversion vive aca, en el borde: asi ninguna vista
        // del catalogo puede olvidarse de aplicarla ni usar otra cotizacion.
        $rates = app(ExchangeRate::class);
        $rate = $rates->current();

        return [
            // ── Campos escalares ───────────────────────────────────────────────
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'code' => $this->code,

            // precio ya convertido a bolivianos, como string, para coincidir
            // con el tipo CatalogProduct del frontend: price: string
            'price' => number_format($rates->toBs($this->price, $rate), 2, '.', ''),
            'sale_price' => $this->sale_price !== null
                ? number_format($rates->toBs($this->sale_price, $rate), 2, '.', '')
                : null,

            // El precio de lista, por si una vista necesita mostrarlo o
            // auditarlo; el catalogo publico usa los de arriba.
            'price_usd' => (string) $this->price,
            'sale_price_usd' => $this->sale_price !== null ? (string) $this->sale_price : null,
            'exchange_rate' => $rate,

            'stock' => (int) $this->stock,
            'unit' => $this->unit,
            'short_description' => $this->short_description,
            'description' => $this->description,

            'is_featured' => (bool) $this->is_featured,
            'is_active' => (bool) $this->is_active,

            // Computed accessors definidos en Product
            'current_price' => $rates->toBs($this->current_price, $rate),
            'is_on_sale' => (bool) $this->is_on_sale,

            'created_at' => $this->created_at?->toIso8601String(),

            // ── Relaciones condicionales ───────────────────────────────────────

            // HasOne → imagen principal (cargada en Index / Home con mainImage)
            // Se expone como `main_image` (snake_case que Inertia envía al frontend)
            // y como `mainImage` (camelCase que algunos componentes usan como alias).
            'main_image' => $this->whenLoaded(
                'mainImage',
                fn () => $this->resource->relationLoaded('mainImage') && $this->resource->mainImage
                    ? new ProductImageResource($this->resource->mainImage)
                    : null
            ),
            'mainImage' => $this->whenLoaded(
                'mainImage',
                fn () => $this->resource->relationLoaded('mainImage') && $this->resource->mainImage
                    ? new ProductImageResource($this->resource->mainImage)
                    : null
            ),

            // HasMany → galería completa (cargada en Show con images)
            'images' => ProductImageResource::collection(
                $this->whenLoaded('images')
            ),

            // BelongsTo → categoría
            'category' => $this->whenLoaded(
                'category',
                fn () => $this->resource->category
                    ? new CategoryResource($this->resource->category)
                    : null
            ),

            // BelongsTo → marca
            'brand' => $this->whenLoaded(
                'brand',
                fn () => $this->resource->brand
                    ? new BrandResource($this->resource->brand)
                    : null
            ),

            // HasMany → atributos técnicos (cargados solo en Show)
            // Solo exponemos key+value que es lo que usa la vista de detalle.
            'attributes' => $this->whenLoaded(
                'attributes',
                fn () => $this->resource->getRelation('attributes')
                    ->map(fn ($a) => ['key' => $a->key, 'value' => $a->value])
                    ->values()
            ),
        ];
    }
}

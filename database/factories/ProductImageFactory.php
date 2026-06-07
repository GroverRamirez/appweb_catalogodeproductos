<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'producto_id' => Product::factory(),
            'ruta' => fake()->randomElement([
                'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&h=900&q=80',
                'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&h=900&q=80',
                'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&h=900&q=80',
                'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&h=900&q=80',
                'https://images.unsplash.com/photo-1602143407151-7111542de6e8?auto=format&fit=crop&w=900&h=900&q=80',
            ]),
            'texto_alternativo' => fake()->sentence(3),
            'orden' => 0,
            'principal' => false,
        ];
    }

    public function main(): static
    {
        return $this->state(fn () => ['principal' => true, 'orden' => 0]);
    }
}

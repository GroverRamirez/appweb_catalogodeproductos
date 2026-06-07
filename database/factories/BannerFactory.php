<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'titulo' => fake()->catchPhrase(),
            'subtitulo' => fake()->sentence(8),
            'imagen' => fake()->randomElement([
                'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1800&h=700&q=85',
                'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=1800&h=700&q=85',
                'https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=1800&h=700&q=85',
                'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=1800&h=700&q=85',
            ]),
            'enlace' => '/catalogo',
            'texto_cta' => fake()->randomElement(['Ver mas', 'Comprar ahora', 'Descubrir', 'Explorar']),
            'orden' => fake()->numberBetween(0, 10),
            'activo' => true,
        ];
    }
}

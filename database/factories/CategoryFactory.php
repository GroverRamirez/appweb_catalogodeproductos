<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'nombre' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'descripcion' => fake()->sentence(10),
            'categoria_padre_id' => null,
            'imagen' => null,
            'orden' => fake()->numberBetween(0, 100),
            'activo' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}

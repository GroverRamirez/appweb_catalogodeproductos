<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
            'contacto_nombre' => fake()->name(),
            'telefono' => fake()->numerify('7#######'),
            'email' => fake()->unique()->companyEmail(),
            'direccion' => fake()->address(),
            'notas' => null,
            'activo' => true,
        ];
    }
}

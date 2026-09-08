<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'nombre' => fake()->name(),
            'telefono' => (string) fake()->unique()->numberBetween(70000000, 79999999),
            'carnet_identidad' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'email' => fake()->unique()->safeEmail(),
            'direccion' => fake()->streetAddress(),
            'notas' => null,
            'activo' => true,
        ];
    }
}

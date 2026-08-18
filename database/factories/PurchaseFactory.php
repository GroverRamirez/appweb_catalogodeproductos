<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'proveedor_id' => Supplier::factory(),
            'numero_referencia' => 'FAC-'.fake()->unique()->numberBetween(1000, 999999),
            'estado' => 'confirmada',
            'costo_total' => fake()->randomFloat(2, 10, 5000),
            'notas' => null,
            'creado_por' => null,
        ];
    }
}

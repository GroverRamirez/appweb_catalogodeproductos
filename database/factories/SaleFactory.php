<?php

namespace Database\Factories;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 20, 3000);

        return [
            'numero_recibo' => fake()->unique()->numberBetween(1, 999999),
            'consulta_id' => null,
            'origen' => 'mostrador',
            'cliente_nombre' => fake()->name(),
            'cliente_telefono' => fake()->numerify('7#######'),
            'metodo_pago' => 'efectivo',
            'subtotal' => $total,
            'descuento_monto' => 0,
            'total' => $total,
            'estado' => 'confirmada',
            'notas' => null,
            'creado_por' => null,
        ];
    }
}

<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

// InnoDB solo indexa para FULLTEXT las filas ya confirmadas (COMMIT). Pest
// envuelve cada test en una transacción sin commit (ver tests/Pest.php),
// así que en MySQL MATCH()/AGAINST() nunca vería los productos creados acá
// sin forzar un commit real. Reabrimos la transacción para no romper el
// rollback de limpieza del framework, y al final borramos a mano lo que
// quedó confirmado para no dejar residuos a los tests siguientes.
test('catalog index filters products by search term', function () {
    $products = collect([
        Product::factory()->create(['nombre' => 'Laptop Gamer Ultra', 'activo' => true]),
        Product::factory()->create(['nombre' => 'Mouse Inalambrico', 'activo' => true]),
        Product::factory()->create(['nombre' => 'Teclado Mecanico', 'activo' => true]),
    ]);

    $isMysql = DB::connection()->getDriverName() === 'mysql';

    if ($isMysql) {
        DB::commit();
        DB::beginTransaction();
    }

    try {
        $this->get(route('catalog.index', ['q' => 'Mouse']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.total', 1)
            );
    } finally {
        if ($isMysql) {
            Product::whereIn('id', $products->pluck('id'))->forceDelete();
            DB::commit();
            DB::beginTransaction();
        }
    }
});

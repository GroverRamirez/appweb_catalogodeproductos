<?php

use App\Models\Category;
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

test('catalog suggestions returns empty when query is under two characters', function () {
    $this->getJson(route('catalog.suggestions', ['q' => 'a']))
        ->assertOk()
        ->assertJson([
            'products' => [],
            'categories' => [],
            'total' => 0,
        ]);
});

test('catalog suggestions returns matching products and categories', function () {
    $category = Category::factory()->create([
        'nombre' => 'Mouse y Teclados',
        'activo' => true,
    ]);

    $product = Product::factory()->create([
        'nombre' => 'Mouse Ergonómico Inalámbrico',
        'categoria_id' => $category->id,
        'activo' => true,
        'codigo' => 'MS-ERG-01',
    ]);

    $isMysql = DB::connection()->getDriverName() === 'mysql';
    if ($isMysql) {
        DB::commit();
        DB::beginTransaction();
    }

    try {
        $response = $this->getJson(route('catalog.suggestions', ['q' => 'Mouse']))
            ->assertOk();

        $data = $response->json();
        expect($data)->toHaveKeys(['products', 'categories', 'total']);
        expect($data['categories'])->not->toBeEmpty();
        expect($data['categories'][0]['name'])->toBe('Mouse y Teclados');
        expect($data['products'])->not->toBeEmpty();
        expect($data['products'][0]['name'])->toBe('Mouse Ergonómico Inalámbrico');
    } finally {
        if ($isMysql) {
            $product->forceDelete();
            $category->forceDelete();
            DB::commit();
            DB::beginTransaction();
        }
    }
});

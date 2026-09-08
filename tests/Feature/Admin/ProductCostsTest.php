<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

test('the costs screen lists only the products missing a cost', function () {
    $sinCosto = Product::factory()->create(['nombre' => 'Sin costo', 'costo' => null]);
    $costoCero = Product::factory()->create(['nombre' => 'Costo cero', 'costo' => 0]);
    Product::factory()->create(['nombre' => 'Con costo', 'costo' => 30]);

    $this->actingAs($this->owner)
        ->get(route('admin.products.costs.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/products/Costs')
            ->has('products', 2)
            ->where('missingCount', 2)
            ->where('totalCount', 3)
            ->where('showAll', false)
            ->etc()
        );

    expect([$sinCosto->id, $costoCero->id])->toHaveCount(2);
});

test('the costs screen can show every product with all=1', function () {
    Product::factory()->count(2)->create(['costo' => null]);
    Product::factory()->create(['costo' => 30]);

    $this->actingAs($this->owner)
        ->get(route('admin.products.costs.edit', ['all' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 3)
            ->where('showAll', true)
            ->etc()
        );
});

test('costs are saved in bulk', function () {
    $a = Product::factory()->create(['costo' => null]);
    $b = Product::factory()->create(['costo' => null]);

    $this->actingAs($this->owner)
        ->patch(route('admin.products.costs.update'), [
            'items' => [
                ['id' => $a->id, 'cost' => 12.5],
                ['id' => $b->id, 'cost' => 99],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($a->fresh()->cost)->toEqual(12.5);
    expect($b->fresh()->cost)->toEqual(99.0);
});

test('a blank cost is skipped and never wipes an existing one', function () {
    $conCosto = Product::factory()->create(['costo' => 40]);
    $aCargar = Product::factory()->create(['costo' => null]);

    $this->actingAs($this->owner)
        ->patch(route('admin.products.costs.update'), [
            'items' => [
                // Fila en blanco: "todavía no sé el costo", no "borralo".
                ['id' => $conCosto->id, 'cost' => null],
                ['id' => $aCargar->id, 'cost' => 7.25],
            ],
        ])
        ->assertRedirect();

    expect($conCosto->fresh()->cost)->toEqual(40.0);
    expect($aCargar->fresh()->cost)->toEqual(7.25);
});

test('a negative cost is rejected', function () {
    $product = Product::factory()->create(['costo' => null]);

    $this->actingAs($this->owner)
        ->patch(route('admin.products.costs.update'), [
            'items' => [['id' => $product->id, 'cost' => -5]],
        ])
        ->assertSessionHasErrors('items.0.cost');

    expect($product->fresh()->cost)->toBeNull();
});

test('a user without inventory.adjust cannot save costs', function () {
    $product = Product::factory()->create(['costo' => null]);
    $sinPermiso = User::factory()->create();
    $sinPermiso->assignRole('cliente');

    $this->actingAs($sinPermiso)
        ->patch(route('admin.products.costs.update'), [
            'items' => [['id' => $product->id, 'cost' => 10]],
        ])
        ->assertForbidden();

    expect($product->fresh()->cost)->toBeNull();
});

test('saving a cost refreshes the dashboard inventory value', function () {
    $product = Product::factory()->create(['stock' => 4, 'costo' => null]);

    // Primero se cachea el valor sin costo...
    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.inventory_cost', fn ($v) => (float) $v === 0.0)
            ->etc()
        );

    $this->actingAs($this->owner)
        ->patch(route('admin.products.costs.update'), [
            'items' => [['id' => $product->id, 'cost' => 25]],
        ]);

    // ...y tras guardar el costo el panel tiene que reflejarlo, no servir cache vieja.
    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.inventory_cost', fn ($v) => (float) $v === 100.0) // 4 * 25
            ->where('stats.products_without_cost', 0)
            ->etc()
        );
});

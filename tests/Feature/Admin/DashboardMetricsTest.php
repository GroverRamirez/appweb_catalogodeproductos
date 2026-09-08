<?php

use App\Models\Product;
use App\Models\Purchase;
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

/** Los montos viajan como number en JSON (110.0 se serializa como 110). */
function numEq(float $expected): Closure
{
    return fn ($value) => (float) $value === $expected;
}

test('the dashboard values the inventory at cost and at list price', function () {
    Product::factory()->create(['stock' => 10, 'costo' => 5, 'precio' => 12, 'precio_oferta' => null]);
    Product::factory()->create(['stock' => 3, 'costo' => 20, 'precio' => 50, 'precio_oferta' => null]);
    // Sin costo: suma al valor de venta pero no al de costo, y se cuenta aparte.
    Product::factory()->create(['stock' => 4, 'costo' => null, 'precio' => 10, 'precio_oferta' => null]);

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->where('stats.inventory_cost', numEq(110.0))    // 10*5 + 3*20 + 4*0
            ->where('stats.inventory_retail', numEq(310.0))  // 10*12 + 3*50 + 4*10
            ->where('stats.products_without_cost', 1)
            ->where('stats.products_total', 3)
            ->etc()
        );
});

test('the inventory is valued at the sale price when a product is on sale', function () {
    Product::factory()->create([
        'stock' => 2,
        'costo' => 10,
        'precio' => 100,
        'precio_oferta' => 80,
    ]);

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.inventory_retail', numEq(160.0)) // 2 * 80, no 2 * 100
            ->etc()
        );
});

test('voided purchases are excluded from the 30 day spend', function () {
    Purchase::factory()->create(['estado' => 'confirmada', 'costo_total' => 1000]);
    Purchase::factory()->create(['estado' => 'confirmada', 'costo_total' => 500]);
    Purchase::factory()->create(['estado' => 'anulada', 'costo_total' => 9999]);

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.purchases_spend_30d', numEq(1500.0))
            // El conteo sí incluye la anulada: son 3 compras registradas.
            ->where('stats.purchases_count_30d', 3)
            ->etc()
        );
});

test('purchases older than the 30 day window are excluded from the spend', function () {
    Purchase::factory()->create(['estado' => 'confirmada', 'costo_total' => 700]);
    Purchase::factory()->create([
        'estado' => 'confirmada',
        'costo_total' => 400,
        'created_at' => now()->subDays(45),
    ]);

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.purchases_spend_30d', numEq(700.0))
            ->etc()
        );
});

test('the products list can be filtered to those missing a cost', function () {
    $withCost = Product::factory()->create(['costo' => 15]);
    $nullCost = Product::factory()->create(['costo' => null]);
    $zeroCost = Product::factory()->create(['costo' => 0]);

    $response = $this->actingAs($this->owner)
        ->get(route('admin.products.index', ['no_cost' => 1]))
        ->assertOk();

    // Sin asumir orden: `latest()` no desempata filas creadas en el mismo segundo.
    $ids = collect($response->viewData('page')['props']['products']['data'])
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    expect($ids)->toBe([$nullCost->id, $zeroCost->id]);
    expect($ids)->not->toContain($withCost->id);
});

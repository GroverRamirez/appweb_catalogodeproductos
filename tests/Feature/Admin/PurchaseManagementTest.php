<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

test('registering a purchase sums stock, updates cost and computes the total on the server', function () {
    $supplier = Supplier::factory()->create();
    $productA = Product::factory()->create(['stock' => 5, 'costo' => 10]);
    $productB = Product::factory()->create(['stock' => 2, 'costo' => 20]);

    $this->actingAs($this->owner)
        ->post(route('admin.purchases.store'), [
            'supplier_id' => $supplier->id,
            'reference_number' => 'FAC-001',
            // El total enviado por el cliente debe ignorarse por completo.
            'total_cost' => 999999,
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 3, 'unit_cost' => 12.5],
                ['product_id' => $productB->id, 'quantity' => 4, 'unit_cost' => 18],
            ],
        ])
        ->assertRedirect(route('admin.purchases.index'))
        ->assertSessionHas('success');

    expect($productA->fresh())
        ->stock->toBe(8) // 5 + 3
        ->cost->toEqual(12.5);

    expect($productB->fresh())
        ->stock->toBe(6) // 2 + 4
        ->cost->toEqual(18.0);

    $purchase = Purchase::first();
    expect((float) $purchase->total_cost)->toBe(3 * 12.5 + 4 * 18.0);
    expect($purchase->status)->toBe('confirmada');
    expect($purchase->items)->toHaveCount(2);
});

test('voiding a confirmed purchase reverts the stock it added', function () {
    $product = Product::factory()->create(['stock' => 5]);

    $this->actingAs($this->owner)
        ->post(route('admin.purchases.store'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 5],
            ],
        ]);

    $purchase = Purchase::first();
    expect($product->fresh()->stock)->toBe(15);

    $this->actingAs($this->owner)
        ->patch(route('admin.purchases.void', $purchase))
        ->assertRedirect(route('admin.purchases.show', $purchase));

    expect($product->fresh()->stock)->toBe(5);
    expect($purchase->fresh()->status)->toBe('anulada');
    expect($purchase->fresh()->voided_by)->toBe($this->owner->id);
});

test('the purchases index can be filtered by status and reference', function () {
    $confirmed = Purchase::factory()->create(['numero_referencia' => 'FAC-100', 'estado' => 'confirmada']);
    Purchase::factory()->create(['numero_referencia' => 'FAC-200', 'estado' => 'anulada']);

    $this->actingAs($this->owner)
        ->get(route('admin.purchases.index', ['status' => 'confirmada']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/purchases/Index')
            ->has('purchases.data', 1)
            ->where('purchases.data.0.id', $confirmed->id)
        );

    $this->actingAs($this->owner)
        ->get(route('admin.purchases.index', ['q' => 'FAC-200']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('purchases.data', 1)
            ->where('purchases.data.0.reference_number', 'FAC-200')
        );
});

test('a user without inventory.adjust cannot register a purchase', function () {
    $product = Product::factory()->create();
    $noAccess = User::factory()->create();
    $noAccess->assignRole('cliente');

    $this->actingAs($noAccess)
        ->post(route('admin.purchases.store'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 1],
            ],
        ])
        ->assertForbidden();

    expect($product->fresh()->stock)->toBe($product->stock);
});

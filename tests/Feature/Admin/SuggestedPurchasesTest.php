<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
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

test('suggested purchases lists low stock products grouped by supplier', function () {
    $supplier = Supplier::factory()->create(['nombre' => 'Distribuidora Global']);

    $productLow = Product::factory()->create([
        'codigo' => 'PROD-001',
        'nombre' => 'Multímetro Digital',
        'stock' => 2,
        'stock_minimo' => 10,
        'costo' => 15.00,
        'activo' => true,
    ]);

    $productNormal = Product::factory()->create([
        'codigo' => 'PROD-002',
        'nombre' => 'Osciloscopio',
        'stock' => 20,
        'stock_minimo' => 5,
        'activo' => true,
    ]);

    // Registrar compra previa confirmada para vincular el producto con el proveedor
    $purchase = Purchase::factory()->create([
        'proveedor_id' => $supplier->id,
        'estado' => 'confirmada',
    ]);
    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'product_id' => $productLow->id,
        'product_name_snapshot' => $productLow->name,
        'product_code_snapshot' => $productLow->code,
        'quantity' => 10,
        'unit_cost' => 14.50,
        'previous_cost' => 15.00,
    ]);

    $this->actingAs($this->owner)
        ->get(route('admin.purchases.suggested'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/purchases/Suggested')
            ->where('totalProducts', 1)
            ->where('totalUnits', 18) // (10 * 2) - 2 = 18
            ->has('groups', 1)
            ->where('groups.0.supplier.name', 'Distribuidora Global')
            ->where('groups.0.items.0.product_id', $productLow->id)
            ->where('groups.0.items.0.suggested_quantity', 18)
            ->where('groups.0.items.0.unit_cost', 14.50)
            ->etc()
        );
});

test('suggested purchases exports csv correctly', function () {
    $productLow = Product::factory()->create([
        'codigo' => 'SKU-TEST-99',
        'nombre' => 'Cables de prueba',
        'stock' => 1,
        'stock_minimo' => 5,
        'costo' => 3.50,
        'activo' => true,
    ]);

    $response = $this->actingAs($this->owner)
        ->get(route('admin.purchases.suggested.export'))
        ->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();
    expect($content)->toContain('Código SKU');
    expect($content)->toContain('SKU-TEST-99');
    expect($content)->toContain('Cables de prueba');
});

test('purchase create accepts prefilled items from suggestion', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create(['costo' => 25.00]);

    $items = [
        ['product_id' => $product->id, 'quantity' => 15, 'unit_cost' => 25.00],
    ];

    $this->actingAs($this->owner)
        ->get(route('admin.purchases.create', [
            'supplier_id' => $supplier->id,
            'items' => json_encode($items),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/purchases/Create')
            ->where('initialSupplierId', $supplier->id)
            ->where('initialItems.0.product_id', $product->id)
            ->where('initialItems.0.quantity', 15)
            ->where('initialItems.0.unit_cost', fn ($val) => (float) $val === 25.0)
            ->etc()
        );
});

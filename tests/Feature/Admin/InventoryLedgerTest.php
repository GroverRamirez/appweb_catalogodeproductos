<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
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

/** Suma del kardex de un producto: tiene que ser siempre su stock. */
function ledgerBalance(Product $product): int
{
    return (int) InventoryMovement::where('producto_id', $product->id)->sum('cantidad');
}

test('una venta deja su movimiento con el saldo corrido', function () {
    $product = Product::factory()->create(['stock' => 0]);

    // El stock parte de un ajuste, como el saldo inicial del kardex.
    $this->actingAs($this->owner)->post(route('admin.products.adjust', $product), [
        'new_stock' => 10,
        'reason' => 'Conteo inicial',
    ]);

    $this->actingAs($this->owner)->post(route('admin.sales.store'), [
        'payment_method' => 'efectivo',
        'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 50]],
    ]);

    $venta = InventoryMovement::where('producto_id', $product->id)
        ->where('tipo', InventoryMovement::TYPE_SALE)
        ->firstOrFail();

    expect($venta->quantity)->toBe(-3);
    expect($venta->previous_stock)->toBe(10);
    expect($venta->resulting_stock)->toBe(7);
    expect($venta->source_id)->toBe(Sale::first()->id);

    expect($product->fresh()->stock)->toBe(7);
    expect(ledgerBalance($product))->toBe(7);
});

test('anular la venta deja su propio movimiento y el saldo vuelve', function () {
    $product = Product::factory()->create(['stock' => 0]);

    $this->actingAs($this->owner)->post(route('admin.products.adjust', $product), [
        'new_stock' => 10, 'reason' => 'Conteo inicial',
    ]);

    $this->actingAs($this->owner)->post(route('admin.sales.store'), [
        'payment_method' => 'efectivo',
        'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 25]],
    ]);

    $this->actingAs($this->owner)->patch(route('admin.sales.void', Sale::first()));

    expect(InventoryMovement::where('producto_id', $product->id)
        ->where('tipo', InventoryMovement::TYPE_SALE_VOID)
        ->value('cantidad'))->toBe(4);

    expect($product->fresh()->stock)->toBe(10);
    expect(ledgerBalance($product))->toBe(10);
});

test('la compra y su anulacion quedan registradas', function () {
    $product = Product::factory()->create(['stock' => 0]);
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->owner)->post(route('admin.purchases.store'), [
        'supplier_id' => $supplier->id,
        'items' => [['product_id' => $product->id, 'quantity' => 6, 'unit_cost' => 20]],
    ]);

    expect($product->fresh()->stock)->toBe(6);
    expect(InventoryMovement::where('producto_id', $product->id)
        ->where('tipo', InventoryMovement::TYPE_PURCHASE)
        ->value('cantidad'))->toBe(6);

    $this->actingAs($this->owner)->patch(route('admin.purchases.void', Purchase::first()));

    expect(InventoryMovement::where('producto_id', $product->id)
        ->where('tipo', InventoryMovement::TYPE_PURCHASE_VOID)
        ->value('cantidad'))->toBe(-6);

    expect($product->fresh()->stock)->toBe(0);
    expect(ledgerBalance($product))->toBe(0);
});

test('el ajuste manual lleva el stock al valor contado y guarda el motivo', function () {
    $product = Product::factory()->create(['stock' => 0]);

    $this->actingAs($this->owner)
        ->post(route('admin.products.adjust', $product), [
            'new_stock' => 12,
            'reason' => 'Conteo físico de estantería',
        ])
        ->assertRedirect(route('admin.products.inventory', $product));

    $movimiento = InventoryMovement::where('producto_id', $product->id)->firstOrFail();

    expect($movimiento->type)->toBe(InventoryMovement::TYPE_ADJUSTMENT);
    expect($movimiento->quantity)->toBe(12);
    expect($movimiento->reason)->toBe('Conteo físico de estantería');
    expect($movimiento->user_id)->toBe($this->owner->id);
    expect($product->fresh()->stock)->toBe(12);
});

test('un ajuste sin motivo se rechaza', function () {
    $product = Product::factory()->create(['stock' => 5]);

    $this->actingAs($this->owner)
        ->post(route('admin.products.adjust', $product), ['new_stock' => 9])
        ->assertSessionHasErrors('reason');

    expect($product->fresh()->stock)->toBe(5);
    expect(InventoryMovement::count())->toBe(0);
});

test('un ajuste que no cambia nada no ensucia el kardex', function () {
    $product = Product::factory()->create(['stock' => 5]);

    $this->actingAs($this->owner)->post(route('admin.products.adjust', $product), [
        'new_stock' => 5,
        'reason' => 'Conteo sin diferencias',
    ]);

    expect(InventoryMovement::count())->toBe(0);
});

test('el formulario de producto ya no puede escribir el stock', function () {
    $product = Product::factory()->create(['stock' => 7, 'nombre' => 'Cámara', 'codigo' => 'C-1']);

    $this->actingAs($this->owner)->patch(route('admin.products.update', $product), [
        'code' => 'C-1',
        'name' => 'Cámara renombrada',
        'price' => 100,
        'stock' => 999,
    ]);

    expect($product->fresh()->name)->toBe('Cámara renombrada');
    expect($product->fresh()->stock)->toBe(7);
});

test('crear un producto con stock inicial lo registra como movimiento', function () {
    $this->actingAs($this->owner)->post(route('admin.products.store'), [
        'code' => 'NUEVO-1',
        'name' => 'Producto nuevo',
        'price' => 10,
        'stock' => 25,
    ]);

    $product = Product::where('codigo', 'NUEVO-1')->firstOrFail();

    expect($product->stock)->toBe(25);
    expect(ledgerBalance($product))->toBe(25);
    expect(InventoryMovement::where('producto_id', $product->id)->value('motivo'))
        ->toBe('Carga inicial del producto');
});

test('un vendedor ve el kardex pero no puede ajustar', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');
    $product = Product::factory()->create(['stock' => 3]);

    // El vendedor tiene inventory.view e inventory.adjust por RoleSeeder, así
    // que el que no puede es el cliente, sin permisos de inventario.
    $this->actingAs($vendedor)
        ->get(route('admin.products.inventory', $product))
        ->assertOk();

    $cliente = User::factory()->create();
    $cliente->assignRole('cliente');

    $this->actingAs($cliente)
        ->post(route('admin.products.adjust', $product), [
            'new_stock' => 99, 'reason' => 'No debería poder',
        ])
        ->assertForbidden();

    expect($product->fresh()->stock)->toBe(3);
});

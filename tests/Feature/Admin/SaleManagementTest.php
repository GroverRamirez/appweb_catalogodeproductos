<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

test('registering a sale subtracts stock, freezes the cost and computes the total on the server', function () {
    $productA = Product::factory()->create(['stock' => 10, 'costo' => 40, 'precio' => 100]);
    $productB = Product::factory()->create(['stock' => 5, 'costo' => 20, 'precio' => 60]);

    $this->actingAs($this->owner)
        ->post(route('admin.sales.store'), [
            'customer_name' => 'Juan Perez',
            'customer_phone' => '70000000',
            'payment_method' => 'efectivo',
            'discount_amount' => 15,
            // Los montos enviados por el cliente deben ignorarse por completo.
            'subtotal' => 999999,
            'total' => 999999,
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 2, 'unit_price' => 100],
                ['product_id' => $productB->id, 'quantity' => 3, 'unit_price' => 55],
            ],
        ])
        ->assertSessionHas('success');

    expect($productA->fresh()->stock)->toBe(8);  // 10 - 2
    expect($productB->fresh()->stock)->toBe(2);  // 5 - 3

    $sale = Sale::first();
    expect((float) $sale->subtotal)->toBe(2 * 100.0 + 3 * 55.0); // 365
    expect((float) $sale->discount_amount)->toBe(15.0);
    expect((float) $sale->total)->toBe(350.0);
    expect($sale->status)->toBe('confirmada');
    expect($sale->origin)->toBe('mostrador');
    expect($sale->items)->toHaveCount(2);

    // El costo queda congelado con el vigente al momento de vender.
    $lineA = $sale->items->firstWhere('product_id', $productA->id);
    expect((float) $lineA->unit_cost)->toBe(40.0);
});

test('the cost frozen on the line survives a later cost change on the product', function () {
    // Es la razon de ser de costo_unitario: sin el, el margen historico
    // cambiaria cada vez que una compra nueva pisa el costo del producto.
    $product = Product::factory()->create(['stock' => 10, 'costo' => 30, 'precio' => 90]);

    $this->actingAs($this->owner)->post(route('admin.sales.store'), [
        'payment_method' => 'efectivo',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 90]],
    ]);

    $product->update(['costo' => 75]);

    expect((float) Sale::first()->items->first()->unit_cost)->toBe(30.0);
});

test('a discount bigger than the subtotal is rejected and nothing is written', function () {
    $product = Product::factory()->create(['stock' => 10, 'precio' => 50]);

    $this->actingAs($this->owner)
        ->post(route('admin.sales.store'), [
            'payment_method' => 'efectivo',
            'discount_amount' => 500,
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => 50]],
        ])
        ->assertSessionHasErrors('discount_amount');

    // La transaccion se revierte entera: ni venta ni movimiento de stock.
    expect(Sale::count())->toBe(0);
    expect($product->fresh()->stock)->toBe(10);
});

test('receipt numbers are correlative and start at one', function () {
    $product = Product::factory()->create(['stock' => 100, 'precio' => 10]);

    foreach (range(1, 3) as $i) {
        $this->actingAs($this->owner)->post(route('admin.sales.store'), [
            'payment_method' => 'efectivo',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
        ]);
    }

    expect(Sale::orderBy('id')->pluck('numero_recibo')->all())->toBe([1, 2, 3]);
});

test('voiding a confirmed sale gives the stock back', function () {
    $product = Product::factory()->create(['stock' => 10, 'precio' => 25]);

    $this->actingAs($this->owner)->post(route('admin.sales.store'), [
        'payment_method' => 'qr',
        'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 25]],
    ]);

    $sale = Sale::first();
    expect($product->fresh()->stock)->toBe(6);

    $this->actingAs($this->owner)
        ->patch(route('admin.sales.void', $sale))
        ->assertRedirect(route('admin.sales.show', $sale));

    expect($product->fresh()->stock)->toBe(10);
    expect($sale->fresh()->status)->toBe('anulada');
    expect($sale->fresh()->voided_by)->toBe($this->owner->id);
});

test('voiding an already voided sale fails', function () {
    $product = Product::factory()->create(['stock' => 10, 'precio' => 25]);

    $this->actingAs($this->owner)->post(route('admin.sales.store'), [
        'payment_method' => 'efectivo',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 25]],
    ]);

    $sale = Sale::first();
    $this->actingAs($this->owner)->patch(route('admin.sales.void', $sale));

    $this->actingAs($this->owner)
        ->patch(route('admin.sales.void', $sale))
        ->assertStatus(422);

    // Y el stock no se devolvio dos veces.
    expect($product->fresh()->stock)->toBe(10);
});

test('selling more than the stock is allowed and leaves it negative', function () {
    // Mismo criterio que la anulacion de compras: el stock negativo es la
    // senal honesta de que el inventario cargado no coincide con la realidad.
    $product = Product::factory()->create(['stock' => 2, 'precio' => 10]);

    $this->actingAs($this->owner)
        ->post(route('admin.sales.store'), [
            'payment_method' => 'efectivo',
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 10]],
        ])
        ->assertSessionHas('success');

    expect($product->fresh()->stock)->toBe(-3);
});

test('the sales index can be filtered by status and searched by receipt', function () {
    $confirmada = Sale::factory()->create(['numero_recibo' => 100, 'estado' => 'confirmada']);
    Sale::factory()->create(['numero_recibo' => 200, 'estado' => 'anulada']);

    $this->actingAs($this->owner)
        ->get(route('admin.sales.index', ['status' => 'confirmada']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/sales/Index')
            ->has('sales.data', 1)
            ->where('sales.data.0.id', $confirmada->id)
        );

    $this->actingAs($this->owner)
        ->get(route('admin.sales.index', ['q' => '200']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sales.data', 1)
            ->where('sales.data.0.receipt_number', 200)
        );
});

test('searching by receipt still respects the status filter', function () {
    // Misma regresion que se corrigio en compras: sin agrupar el OR, SQL lee
    // "recibo LIKE ? OR (cliente AND estado = ?)" y el filtro se pierde.
    Sale::factory()->create(['numero_recibo' => 100, 'estado' => 'confirmada']);
    $anulada = Sale::factory()->create(['numero_recibo' => 200, 'estado' => 'anulada']);

    $this->actingAs($this->owner)
        ->get(route('admin.sales.index', ['q' => '100', 'status' => 'anulada']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('sales.data', 0));

    $this->actingAs($this->owner)
        ->get(route('admin.sales.index', ['q' => '200', 'status' => 'anulada']))
        ->assertInertia(fn ($page) => $page
            ->has('sales.data', 1)
            ->where('sales.data.0.id', $anulada->id)
        );
});

test('a vendedor can register a sale but cannot void one', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $product = Product::factory()->create(['stock' => 10, 'precio' => 30]);

    $this->actingAs($vendedor)
        ->post(route('admin.sales.store'), [
            'payment_method' => 'efectivo',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 30]],
        ])
        ->assertSessionHas('success');

    $sale = Sale::first();

    $this->actingAs($vendedor)
        ->patch(route('admin.sales.void', $sale))
        ->assertForbidden();

    expect($sale->fresh()->status)->toBe('confirmada');
});

test('a user without sales permissions cannot register a sale', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $noAccess = User::factory()->create();
    $noAccess->assignRole('cliente');

    $this->actingAs($noAccess)
        ->post(route('admin.sales.store'), [
            'payment_method' => 'efectivo',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10]],
        ])
        ->assertForbidden();

    expect($product->fresh()->stock)->toBe(10);
});

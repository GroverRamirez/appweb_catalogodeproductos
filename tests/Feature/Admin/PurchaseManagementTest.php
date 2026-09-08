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

test('searching by reference still respects the status filter', function () {
    // Regresion: el OR del buscador no estaba agrupado, asi que SQL leia
    // "ref LIKE ? OR (proveedor AND estado = ?)" y una compra que matcheaba
    // por referencia se colaba ignorando el filtro de estado.
    Purchase::factory()->create(['numero_referencia' => 'FAC-100', 'estado' => 'confirmada']);
    $anulada = Purchase::factory()->create(['numero_referencia' => 'FAC-200', 'estado' => 'anulada']);

    $this->actingAs($this->owner)
        ->get(route('admin.purchases.index', ['q' => 'FAC-100', 'status' => 'anulada']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('purchases.data', 0));

    // Y la combinacion que si deberia encontrar algo, funciona.
    $this->actingAs($this->owner)
        ->get(route('admin.purchases.index', ['q' => 'FAC-200', 'status' => 'anulada']))
        ->assertInertia(fn ($page) => $page
            ->has('purchases.data', 1)
            ->where('purchases.data.0.id', $anulada->id)
        );
});

test('a zero unit cost never wipes the product known cost', function () {
    // Regresion: el formulario arrancaba el costo en 0, asi que olvidarse de
    // llenarlo dejaba el producto "sin costo" y lo sacaba de la valorizacion.
    $product = Product::factory()->create(['stock' => 10, 'costo' => 50]);

    $this->actingAs($this->owner)
        ->post(route('admin.purchases.store'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 0],
            ],
        ])
        ->assertRedirect(route('admin.purchases.index'));

    $fresh = $product->fresh();

    // El stock si entra (una bonificacion es mercaderia real)...
    expect($fresh->stock)->toBe(12);
    // ...pero el costo conocido queda intacto.
    expect($fresh->cost)->toEqual(50.0);
});

test('a real unit cost does replace the previous one', function () {
    $product = Product::factory()->create(['stock' => 0, 'costo' => 50]);

    $this->actingAs($this->owner)
        ->post(route('admin.purchases.store'), [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 73.5],
            ],
        ]);

    expect($product->fresh()->cost)->toEqual(73.5);
});

test('voiding a purchase also restores the previous cost', function () {
    $product = Product::factory()->create(['stock' => 5, 'costo' => 40]);

    $this->actingAs($this->owner)->post(route('admin.purchases.store'), [
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 90]],
    ]);

    expect($product->fresh()->cost)->toEqual(90.0);

    $purchase = Purchase::first();
    $this->actingAs($this->owner)->patch(route('admin.purchases.void', $purchase));

    $fresh = $product->fresh();
    expect($fresh->stock)->toBe(5);   // stock revertido
    expect($fresh->cost)->toEqual(40.0); // y el costo anterior devuelto
});

test('voiding does not restore the cost if a later purchase already changed it', function () {
    // El caso delicado: si otra compra posterior fijo un costo nuevo, devolver
    // el viejo seria peor que dejar el vigente.
    $product = Product::factory()->create(['stock' => 0, 'costo' => 40]);

    $this->actingAs($this->owner)->post(route('admin.purchases.store'), [
        'reference_number' => 'PRIMERA',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 90]],
    ]);
    $primera = Purchase::where('numero_referencia', 'PRIMERA')->first();

    $this->actingAs($this->owner)->post(route('admin.purchases.store'), [
        'reference_number' => 'SEGUNDA',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 120]],
    ]);

    expect($product->fresh()->cost)->toEqual(120.0);

    // Anular la PRIMERA no debe pisar el costo que dejo la SEGUNDA.
    $this->actingAs($this->owner)->patch(route('admin.purchases.void', $primera));

    expect($product->fresh()->cost)->toEqual(120.0);
});

test('voiding a zero cost purchase leaves the cost untouched', function () {
    // Una linea con costo 0 nunca piso el costo, asi que no hay nada que devolver.
    $product = Product::factory()->create(['stock' => 3, 'costo' => 55]);

    $this->actingAs($this->owner)->post(route('admin.purchases.store'), [
        'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 0]],
    ]);

    $purchase = Purchase::first();
    $this->actingAs($this->owner)->patch(route('admin.purchases.void', $purchase));

    expect($product->fresh()->cost)->toEqual(55.0);
    expect($product->fresh()->stock)->toBe(3);
});

test('the reference check warns about a duplicate for the same supplier', function () {
    $supplier = Supplier::factory()->create();
    $otro = Supplier::factory()->create();

    $purchase = Purchase::factory()->create([
        'numero_referencia' => 'FAC-777',
        'proveedor_id' => $supplier->id,
        'estado' => 'confirmada',
    ]);

    // Mismo remito y mismo proveedor: avisa.
    $this->actingAs($this->owner)
        ->getJson(route('admin.purchases.reference-check', [
            'reference' => 'FAC-777',
            'supplier_id' => $supplier->id,
        ]))
        ->assertOk()
        ->assertJson(['duplicate' => true, 'purchase_id' => $purchase->id]);

    // Mismo remito pero otro proveedor: no es duplicado.
    $this->actingAs($this->owner)
        ->getJson(route('admin.purchases.reference-check', [
            'reference' => 'FAC-777',
            'supplier_id' => $otro->id,
        ]))
        ->assertJson(['duplicate' => false]);

    // Remito distinto: no avisa.
    $this->actingAs($this->owner)
        ->getJson(route('admin.purchases.reference-check', [
            'reference' => 'FAC-888',
            'supplier_id' => $supplier->id,
        ]))
        ->assertJson(['duplicate' => false]);
});

test('a duplicate reference does not block registering the purchase', function () {
    // Es un aviso, no una validacion: el proveedor puede repetir numeracion.
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create(['stock' => 0]);

    Purchase::factory()->create([
        'numero_referencia' => 'FAC-999',
        'proveedor_id' => $supplier->id,
    ]);

    $this->actingAs($this->owner)
        ->post(route('admin.purchases.store'), [
            'supplier_id' => $supplier->id,
            'reference_number' => 'FAC-999',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 10]],
        ])
        ->assertRedirect(route('admin.purchases.index'));

    expect(Purchase::where('numero_referencia', 'FAC-999')->count())->toBe(2);
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

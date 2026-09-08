<?php

use App\Models\Client;
use App\Models\Inquiry;
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

test('el telefono se normaliza al guardar', function () {
    // Sin esto el indice unico no sirve: los tres son la misma persona.
    expect(Client::normalizePhone('70000000'))->toBe('70000000');
    expect(Client::normalizePhone('+591 70000000'))->toBe('70000000');
    expect(Client::normalizePhone('591-70000000'))->toBe('70000000');
    expect(Client::normalizePhone('  '))->toBeNull();

    $client = Client::create(['name' => 'Ana', 'phone' => '+591 7 000 0000']);

    expect($client->fresh()->phone)->toBe('70000000');
});

test('resolveByPhone reusa el cliente en vez de duplicarlo', function () {
    $primero = Client::resolveByPhone('70000000', ['name' => 'Ana']);
    $segundo = Client::resolveByPhone('+591 70000000', ['name' => 'Ana Maria']);

    expect($segundo->id)->toBe($primero->id);
    expect(Client::count())->toBe(1);
});

test('sin telefono no se crea un cliente anonimo', function () {
    // La venta al paso no debe generar una ficha por cada compra.
    expect(Client::resolveByPhone(null, ['name' => 'Consumidor']))->toBeNull();
    expect(Client::resolveByPhone('', ['name' => 'Consumidor']))->toBeNull();
    expect(Client::count())->toBe(0);
});

test('registrar una venta con telefono crea el cliente y lo enlaza', function () {
    $product = Product::factory()->create(['stock' => 10, 'precio' => 50]);

    $this->actingAs($this->owner)->post(route('admin.sales.store'), [
        'customer_name' => 'Ana Quispe',
        'customer_phone' => '+591 70123456',
        'id_card' => '1234567',
        'payment_method' => 'efectivo',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50]],
    ]);

    $client = Client::first();

    expect($client)->not->toBeNull();
    expect($client->name)->toBe('Ana Quispe');
    expect($client->phone)->toBe('70123456');
    expect($client->id_card)->toBe('1234567');

    $sale = Sale::first();
    expect($sale->client_id)->toBe($client->id);
    // El snapshot conserva lo que se escribio en la venta.
    expect($sale->customer_name)->toBe('Ana Quispe');
});

test('dos ventas al mismo telefono comparten un solo cliente', function () {
    $product = Product::factory()->create(['stock' => 10, 'precio' => 50]);

    foreach (['70123456', '591 70123456'] as $telefono) {
        $this->actingAs($this->owner)->post(route('admin.sales.store'), [
            'customer_name' => 'Ana',
            'customer_phone' => $telefono,
            'payment_method' => 'efectivo',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50]],
        ]);
    }

    expect(Client::count())->toBe(1);
    expect(Client::first()->sales()->count())->toBe(2);
});

test('una venta sin telefono queda sin cliente', function () {
    $product = Product::factory()->create(['stock' => 10, 'precio' => 50]);

    $this->actingAs($this->owner)->post(route('admin.sales.store'), [
        'payment_method' => 'efectivo',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50]],
    ]);

    expect(Client::count())->toBe(0);
    expect(Sale::first()->client_id)->toBeNull();
});

test('el snapshot de la venta sobrevive a un cambio de datos del cliente', function () {
    $product = Product::factory()->create(['stock' => 10, 'precio' => 50]);

    $this->actingAs($this->owner)->post(route('admin.sales.store'), [
        'customer_name' => 'Ana Quispe',
        'customer_phone' => '70123456',
        'payment_method' => 'efectivo',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50]],
    ]);

    Client::first()->update(['name' => 'Ana Quispe de Mamani', 'phone' => '79999999']);

    $sale = Sale::first();

    // El recibo emitido sigue diciendo lo que decia.
    expect($sale->customer_name)->toBe('Ana Quispe');
    expect($sale->customer_phone)->toBe('70123456');
    // Pero la ficha actual se alcanza por la FK.
    expect($sale->client->name)->toBe('Ana Quispe de Mamani');
});

test('no se puede registrar dos clientes con el mismo telefono', function () {
    Client::factory()->create(['telefono' => '70123456']);

    $this->actingAs($this->owner)
        ->post(route('admin.clients.store'), [
            'name' => 'Otro',
            // Escrito distinto pero es el mismo numero.
            'phone' => '+591 70123456',
        ])
        ->assertSessionHasErrors('phone');

    expect(Client::count())->toBe(1);
});

test('la ficha del cliente muestra su historial y lo gastado', function () {
    $client = Client::factory()->create();

    Sale::factory()->create(['cliente_id' => $client->id, 'estado' => 'confirmada', 'total' => 100]);
    Sale::factory()->create(['cliente_id' => $client->id, 'estado' => 'confirmada', 'total' => 250]);
    // La anulada no cuenta para lo gastado.
    Sale::factory()->create(['cliente_id' => $client->id, 'estado' => 'anulada', 'total' => 999]);

    $this->actingAs($this->owner)
        ->get(route('admin.clients.show', $client))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/clients/Show')
            ->where('summary.purchases', 2)
            ->where('summary.total_spent', 350)
            ->has('client.sales', 3)
        );
});

test('dar de baja a un cliente conserva su historial', function () {
    $client = Client::factory()->create();
    $sale = Sale::factory()->create(['cliente_id' => $client->id]);

    $this->actingAs($this->owner)
        ->delete(route('admin.clients.destroy', $client))
        ->assertRedirect(route('admin.clients.index'));

    expect(Client::count())->toBe(0);
    expect(Client::withTrashed()->count())->toBe(1);
    // La venta no se queda sin dueño.
    expect($sale->fresh()->client_id)->toBe($client->id);
});

test('un cliente dado de baja que vuelve a comprar se reactiva', function () {
    $client = Client::factory()->create(['telefono' => '70123456']);
    $client->delete();

    $resuelto = Client::resolveByPhone('70123456', ['name' => 'Ana']);

    expect($resuelto->id)->toBe($client->id);
    expect($resuelto->trashed())->toBeFalse();
    expect(Client::count())->toBe(1);
});

test('la consulta marcada como vendida hereda el cliente', function () {
    $client = Client::factory()->create();
    $product = Product::factory()->create(['stock' => 10]);

    $inquiry = Inquiry::create([
        'client_id' => $client->id,
        'customer_name' => $client->name,
        'customer_phone' => $client->phone,
        'status' => 'pendiente',
        'source' => 'web',
    ]);

    $inquiry->items()->create([
        'product_id' => $product->id,
        'product_name_snapshot' => $product->name,
        'product_code_snapshot' => $product->code,
        'quantity' => 1,
        'unit_price' => 100,
    ]);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);

    expect(Sale::first()->client_id)->toBe($client->id);
});

test('un vendedor puede registrar clientes pero no darlos de baja', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $this->actingAs($vendedor)
        ->post(route('admin.clients.store'), ['name' => 'Ana', 'phone' => '70123456'])
        ->assertSessionHas('success');

    $client = Client::first();

    $this->actingAs($vendedor)
        ->delete(route('admin.clients.destroy', $client))
        ->assertForbidden();

    expect(Client::count())->toBe(1);
});

test('buscar por nombre no arrastra a todos los clientes con telefono', function () {
    // Regresion: normalizePhone('ana') da null y la condicion quedaba
    // LIKE '%%', que matchea cualquier telefono no nulo.
    Client::factory()->create(['nombre' => 'Ana Quispe', 'telefono' => '70000001']);
    Client::factory()->create(['nombre' => 'Beto Mamani', 'telefono' => '70000002']);

    $this->actingAs($this->owner)
        ->get(route('admin.clients.index', ['q' => 'Ana']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/clients/Index')
            ->has('clients.data', 1)
            ->where('clients.data.0.name', 'Ana Quispe')
        );
});

test('buscar por telefono lo encuentra escrito de cualquier forma', function () {
    Client::factory()->create(['nombre' => 'Ana Quispe', 'telefono' => '70123456']);
    Client::factory()->create(['nombre' => 'Beto Mamani', 'telefono' => '79999999']);

    $this->actingAs($this->owner)
        ->get(route('admin.clients.index', ['q' => '+591 70123456']))
        ->assertInertia(fn ($page) => $page
            ->has('clients.data', 1)
            ->where('clients.data.0.name', 'Ana Quispe')
        );
});

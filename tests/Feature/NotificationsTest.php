<?php

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExchangeRate;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(ExchangeRate::class)->store(1.0);
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create(['name' => 'Grover Ramirez']);
    $this->owner->assignRole('propietario');
});

/** Notificaciones que viajan en el canal flash de Inertia. */
function flashedToasts(): array
{
    return session(SessionKey::FLASH_DATA)['toasts'] ?? [];
}

test('logging in greets the user by first name', function () {
    $user = User::factory()->create(['name' => 'Ana María López']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect(flashedToasts())->toHaveCount(1)
        ->and(flashedToasts()[0])->toMatchArray([
            'type' => 'success',
            'message' => '¡Hola, Ana!',
        ]);
});

test('a failed login does not greet anyone', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    expect(flashedToasts())->toBe([]);
});

test('session flash messages reach the next page as a one-time notification', function () {
    $this->actingAs($this->owner)
        ->post(route('admin.suppliers.store'), ['name' => 'Proveedor Uno'])
        ->assertSessionHas('success', 'Proveedor creado.');

    $page = $this->get(route('admin.suppliers.index'))->viewData('page');

    expect($page['flash']['toasts'])->toBe([
        ['type' => 'success', 'message' => 'Proveedor creado.'],
    ]);

    // Ya se mostró: la página siguiente no lo repite.
    $next = $this->get(route('admin.suppliers.index'))->viewData('page');

    expect($next['flash'] ?? [])->not->toHaveKey('toasts');
});

test('registering a sale notifies the receipt number and total', function () {
    $product = Product::factory()->create(['stock' => 10, 'costo' => 40, 'precio' => 1250]);

    $this->actingAs($this->owner)
        ->post(route('admin.sales.store'), [
            'payment_method' => 'efectivo',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1250]],
        ])
        ->assertSessionHas('success', fn (array $toast) => str_starts_with($toast['message'], 'Venta N° 0')
            && str_contains($toast['description'], '1.250,00'));
});

test('registering a purchase notifies the supplier and the units that came in', function () {
    $supplier = Supplier::factory()->create(['name' => 'Importadora Sur']);
    $product = Product::factory()->create(['stock' => 0, 'costo' => 10]);

    $this->actingAs($this->owner)
        ->post(route('admin.purchases.store'), [
            'supplier_id' => $supplier->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_cost' => 10]],
        ])
        ->assertSessionHas('success', fn (array $toast) => $toast['message'] === 'Compra a Importadora Sur registrada'
            && str_contains($toast['description'], '3 unidades'));
});

test('a forbidden form submission returns to the form with an error notification', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)
        ->from(route('home'))
        ->withHeader('X-Inertia', 'true')
        ->post(route('admin.suppliers.store'), ['name' => 'X'])
        ->assertRedirect(route('home'))
        ->assertSessionHas('error', 'No tienes permiso para realizar esta acción.');
});

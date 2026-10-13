<?php

use App\Models\Client;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function clienteWeb(): User
{
    $user = User::factory()->create();
    $user->assignRole('cliente');

    return $user;
}

test('un cliente sin CI ni teléfono va a completar sus datos', function () {
    $this->actingAs(clienteWeb())
        ->get(route('catalog.index'))
        ->assertRedirect(route('client-profile.edit'));
});

test('el formulario se muestra', function () {
    $this->actingAs(clienteWeb())
        ->get(route('client-profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/CompleteProfile'));
});

test('los visitantes y el staff navegan sin que se les pida nada', function () {
    $this->get(route('home'))->assertOk();

    $staff = User::factory()->create();
    $staff->assignRole('propietario');

    $this->actingAs($staff)->get(route('home'))->assertOk();
});

test('al guardar crea la ficha enlazada y vuelve adonde iba', function () {
    $user = clienteWeb();

    $this->actingAs($user)->get(route('catalog.index'));

    $this->actingAs($user)
        ->put(route('client-profile.update'), ['id_card' => ' 1234567 lp ', 'phone' => '+591 7000-0001'])
        ->assertRedirect(route('catalog.index'))
        ->assertSessionHas('success');

    $client = Client::where('user_id', $user->id)->sole();
    expect($client->id_card)->toBe('1234567 LP')
        ->and($client->phone)->toBe('70000001')
        ->and(Client::findByIdCard('1234567 lp')->id)->toBe($client->id);

    $this->actingAs($user)->get(route('catalog.index'))->assertOk();
});

test('CI y teléfono son obligatorios', function () {
    $this->actingAs(clienteWeb())
        ->put(route('client-profile.update'), ['id_card' => 'x', 'phone' => '12'])
        ->assertSessionHasErrors(['id_card', 'phone']);
});

test('enlaza la ficha de mostrador que ya tenía ese CI', function () {
    $user = clienteWeb();
    $mostrador = Client::factory()->create(['carnet_identidad' => '7654321', 'telefono' => '71111111']);

    $this->actingAs($user)
        ->put(route('client-profile.update'), ['id_card' => '7654321', 'phone' => '72222222'])
        ->assertSessionHasNoErrors();

    expect($mostrador->fresh()->user_id)->toBe($user->id)
        ->and($mostrador->fresh()->phone)->toBe('72222222')
        ->and(Client::count())->toBe(1);
});

test('si el teléfono es de una ficha de mostrador, se unen en una sola', function () {
    $user = clienteWeb();
    $propia = Client::factory()->create(['user_id' => $user->id, 'carnet_identidad' => null, 'telefono' => null]);
    $mostrador = Client::factory()->create(['telefono' => '73333333']);
    $venta = Sale::factory()->create(['cliente_id' => $mostrador->id]);

    $this->actingAs($user)
        ->put(route('client-profile.update'), ['id_card' => '5555555', 'phone' => '73333333'])
        ->assertSessionHasNoErrors();

    expect($propia->fresh()->phone)->toBe('73333333')
        ->and($venta->fresh()->client_id)->toBe($propia->id)
        ->and($mostrador->fresh()->trashed())->toBeTrue();
});

test('no deja usar el teléfono de otra cuenta', function () {
    $otra = clienteWeb();
    Client::factory()->create(['user_id' => $otra->id, 'telefono' => '74444444']);

    $this->actingAs(clienteWeb())
        ->put(route('client-profile.update'), ['id_card' => '1111111', 'phone' => '74444444'])
        ->assertSessionHasErrors('phone');
});

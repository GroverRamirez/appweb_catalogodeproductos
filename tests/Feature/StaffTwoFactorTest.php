<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('staff without confirmed two factor is redirected to security settings', function (string $role) {
    $user = User::factory()->create(['two_factor_confirmed_at' => null]);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('security.edit'));
})->with(['propietario', 'encargado', 'vendedor']);

test('staff with confirmed two factor can enter the panel', function () {
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole('propietario');

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('customer is not affected by the two factor requirement', function () {
    $user = User::factory()->create(['two_factor_confirmed_at' => null]);
    $user->assignRole('cliente');

    // Cliente no es staff: el dashboard lo redirige al catálogo público, no a seguridad.
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect('/');
});

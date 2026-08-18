<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rules\Password;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

test('creating a user with a weak password fails the default policy', function () {
    // El FormRequest usa Password::default(); definimos aquí la política fuerte
    // (sin uncompromised para no depender de la red) y verificamos que se aplica.
    Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

    $this->actingAs($this->owner)
        ->from(route('admin.users.create'))
        ->post(route('admin.users.store'), [
            'name' => 'Nuevo',
            'email' => 'nuevo@catalogo.test',
            'password' => '123',
            'role' => 'vendedor',
        ])
        ->assertSessionHasErrors('password');

    expect(User::where('email', 'nuevo@catalogo.test')->exists())->toBeFalse();
});

test('creating a user with a strong password succeeds', function () {
    $this->actingAs($this->owner)
        ->post(route('admin.users.store'), [
            'name' => 'Nuevo',
            'email' => 'nuevo@catalogo.test',
            'password' => 'Str0ng-P4ssw0rd!xyz',
            'role' => 'vendedor',
        ])
        ->assertRedirect(route('admin.users.index'));

    expect(User::where('email', 'nuevo@catalogo.test')->exists())->toBeTrue();
});

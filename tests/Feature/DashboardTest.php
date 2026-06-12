<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('customer users are redirected to the public catalog from dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('cliente');

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertRedirect('/');
});

test('staff users are redirected to admin from dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('propietario');

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertRedirect('/admin');
});

test('admin users can access management dashboard', function () {
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole('propietario');

    $response = $this
        ->actingAs($user)
        ->get(route('admin.dashboard'));

    $response->assertOk();
});

test('staff without two factor are redirected to security settings', function () {
    $user = User::factory()->create(['two_factor_confirmed_at' => null]);
    $user->assignRole('propietario');

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('security.edit'));
});

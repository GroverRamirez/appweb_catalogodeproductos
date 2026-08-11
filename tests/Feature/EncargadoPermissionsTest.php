<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->encargado = User::factory()->create();
    $this->encargado->assignRole('encargado');
});

test('encargado can manage the catalog', function () {
    $this->actingAs($this->encargado)
        ->get(route('admin.products.create'))
        ->assertOk();

    $this->actingAs($this->encargado)
        ->get(route('admin.banners.index'))
        ->assertOk();

    $this->actingAs($this->encargado)
        ->get(route('admin.coupons.index'))
        ->assertOk();
});

test('encargado cannot manage users, roles or settings', function () {
    $this->actingAs($this->encargado)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    $this->actingAs($this->encargado)
        ->get(route('admin.roles.index'))
        ->assertForbidden();

    $this->actingAs($this->encargado)
        ->get(route('admin.settings.edit'))
        ->assertForbidden();
});

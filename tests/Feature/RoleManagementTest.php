<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

test('owner can create a role with permissions', function () {
    $this->actingAs($this->owner)
        ->post(route('admin.roles.store'), [
            'name' => 'editor',
            'permissions' => ['products.view', 'products.update'],
        ])
        ->assertRedirect(route('admin.roles.index'));

    $role = Role::findByName('editor', 'web');

    expect($role->hasPermissionTo('products.view'))->toBeTrue();
    expect($role->hasPermissionTo('products.update'))->toBeTrue();
    expect($role->hasPermissionTo('products.delete'))->toBeFalse();
});

test('a custom role with a permission can access the admin panel', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
    $role->givePermissionTo('products.view');

    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $this->actingAs($editor)
        ->get(route('admin.dashboard'))
        ->assertOk();

    $this->actingAs($editor)
        ->get(route('admin.products.index'))
        ->assertOk();

    // Pero no alcanza recursos para los que no tiene permiso.
    $this->actingAs($editor)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('a user without permissions cannot access the admin panel', function () {
    $cliente = User::factory()->create();
    $cliente->assignRole('cliente');

    $this->actingAs($cliente)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('encargado cannot access role management', function () {
    $encargado = User::factory()->create();
    $encargado->assignRole('encargado');

    $this->actingAs($encargado)
        ->get(route('admin.roles.index'))
        ->assertForbidden();
});

test('protected system roles cannot be deleted', function () {
    $propietario = Role::findByName('propietario', 'web');

    $this->actingAs($this->owner)
        ->delete(route('admin.roles.destroy', $propietario))
        ->assertRedirect();

    expect(Role::where('name', 'propietario')->exists())->toBeTrue();
});

test('roles with assigned users cannot be deleted', function () {
    $role = Role::create(['name' => 'temporal', 'guard_name' => 'web']);
    $member = User::factory()->create();
    $member->assignRole('temporal');

    $this->actingAs($this->owner)
        ->delete(route('admin.roles.destroy', $role))
        ->assertSessionHas('error');

    expect(Role::where('name', 'temporal')->exists())->toBeTrue();
});

test('protected system role cannot be renamed or have permissions changed', function () {
    $cliente = Role::findByName('cliente', 'web');

    $this->actingAs($this->owner)
        ->patch(route('admin.roles.update', $cliente), [
            'name' => 'cliente-renombrado',
            'permissions' => ['products.view'],
        ])
        ->assertSessionHasErrors(['name', 'permissions']);

    expect(Role::where('name', 'cliente')->exists())->toBeTrue();
    expect($cliente->fresh()->permissions)->toBeEmpty();
});

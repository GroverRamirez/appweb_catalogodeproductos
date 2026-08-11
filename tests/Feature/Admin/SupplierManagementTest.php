<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

test('suppliers list is visible to a user with inventory.view', function () {
    Supplier::factory()->create(['nombre' => 'Digicorp SRL']);

    $this->actingAs($this->owner)
        ->get(route('admin.suppliers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/suppliers/Index')
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.name', 'Digicorp SRL')
        );
});

test('a supplier can be created', function () {
    $this->actingAs($this->owner)
        ->post(route('admin.suppliers.store'), [
            'name' => 'Proveedor de Prueba',
            'phone' => '77712345',
            'email' => 'contacto@proveedor.test',
        ])
        ->assertRedirect(route('admin.suppliers.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('proveedores', [
        'nombre' => 'Proveedor de Prueba',
        'telefono' => '77712345',
    ]);
});

test('a supplier can be updated', function () {
    $supplier = Supplier::factory()->create(['nombre' => 'Nombre Viejo']);

    $this->actingAs($this->owner)
        ->patch(route('admin.suppliers.update', $supplier), [
            'name' => 'Nombre Nuevo',
        ])
        ->assertRedirect(route('admin.suppliers.index'));

    expect($supplier->fresh()->name)->toBe('Nombre Nuevo');
});

test('a user without inventory.adjust cannot create suppliers', function () {
    $seller = User::factory()->create();
    $seller->assignRole('cliente');

    $this->actingAs($seller)
        ->post(route('admin.suppliers.store'), ['name' => 'X'])
        ->assertForbidden();
});

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

test('contact name errors call the field persona de contacto', function () {
    app()->setLocale('es');

    $this->actingAs($this->owner)
        ->post(route('admin.suppliers.store'), [
            'name' => 'Proveedor de Prueba',
            'contact_name' => str_repeat('a', 151),
        ])
        ->assertSessionHasErrors([
            'contact_name' => 'El campo persona de contacto no debe tener más de 150 caracteres.',
        ]);
});

test('suppliers list includes the address', function () {
    Supplier::factory()->create(['direccion' => 'Av. Busch 1234']);

    $this->actingAs($this->owner)
        ->get(route('admin.suppliers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('suppliers.data.0.address', 'Av. Busch 1234')
        );
});

<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->owner->assignRole('propietario');
});

test('a product can be created with attributes and images', function () {
    Storage::fake('public');

    $this->actingAs($this->owner)
        ->post(route('admin.products.store'), [
            'code' => 'TEST-001',
            'name' => 'Producto de Prueba',
            'price' => 99.9,
            'stock' => 10,
            'attributes' => [
                ['key' => 'Color', 'value' => 'Rojo'],
            ],
            'images' => [
                UploadedFile::fake()->image('foto.jpg', 800, 800),
            ],
        ])
        ->assertRedirect(route('admin.products.index'))
        ->assertSessionHas('success');

    $product = Product::where('codigo', 'TEST-001')->first();
    expect($product)->not->toBeNull();

    $this->assertDatabaseHas('producto_atributos', [
        'producto_id' => $product->id,
        'clave' => 'Color',
        'valor' => 'Rojo',
    ]);

    $this->assertDatabaseHas('producto_imagenes', [
        'producto_id' => $product->id,
        'principal' => true,
    ]);
});

test('a product can be created without images or attributes', function () {
    $this->actingAs($this->owner)
        ->post(route('admin.products.store'), [
            'code' => 'TEST-002',
            'name' => 'Producto Simple',
            'price' => 50,
            'stock' => 5,
        ])
        ->assertRedirect(route('admin.products.index'));

    $this->assertDatabaseHas('productos', [
        'codigo' => 'TEST-002',
        'nombre' => 'Producto Simple',
    ]);
});

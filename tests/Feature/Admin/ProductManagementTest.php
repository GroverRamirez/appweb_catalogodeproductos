<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

test('the new product form includes root categories', function () {
    $category = Category::factory()->create([
        'nombre' => 'Cámaras de Seguridad',
        'categoria_padre_id' => null,
    ]);

    $this->actingAs($this->owner)
        ->get(route('admin.products.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/products/Form')
            ->where('product', null)
            ->has('categories', 1)
            ->where('categories.0.id', $category->id)
            ->where('categories.0.name', 'Cámaras de Seguridad')
            ->has('brands')
        );
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

test('renaming a product resyncs the alt of its images', function () {
    $product = Product::factory()->create(['nombre' => 'Cámara IP Domo 2MP']);

    // Alt autogenerado al subir la imagen: es una copia del nombre.
    $auto = ProductImage::create([
        'product_id' => $product->id,
        'path' => 'products/'.$product->id.'/auto.webp',
        'alt' => 'Cámara IP Domo 2MP',
        'sort_order' => 0,
        'is_main' => true,
    ]);

    // Alt escrito a mano: describe la imagen, no repite el nombre.
    $manual = ProductImage::create([
        'product_id' => $product->id,
        'path' => 'products/'.$product->id.'/manual.webp',
        'alt' => 'Vista lateral con el soporte puesto',
        'sort_order' => 1,
        'is_main' => false,
    ]);

    $product->update(['name' => 'Cámara IP Domo 2MP Full Color']);

    expect($auto->fresh()->alt)->toBe('Cámara IP Domo 2MP Full Color');
    expect($manual->fresh()->alt)->toBe('Vista lateral con el soporte puesto');
});

test('saving a product without renaming it leaves the alt of its images alone', function () {
    $product = Product::factory()->create(['nombre' => 'Cámara IP Domo 2MP']);

    $image = ProductImage::create([
        'product_id' => $product->id,
        'path' => 'products/'.$product->id.'/auto.webp',
        'alt' => 'Cámara IP Domo 2MP',
        'sort_order' => 0,
        'is_main' => true,
    ]);

    $product->update(['price' => 41]);

    expect($image->fresh()->alt)->toBe('Cámara IP Domo 2MP');
});

test('sale price cannot exceed the regular price', function () {
    $this->actingAs($this->owner)
        ->post(route('admin.products.store'), [
            'code' => 'TEST-INVALID-SALE',
            'name' => 'Producto con oferta inválida',
            'price' => 129,
            'sale_price' => 150,
            'stock' => 6,
        ])
        ->assertSessionHasErrors('sale_price');

    $this->assertDatabaseMissing('productos', [
        'codigo' => 'TEST-INVALID-SALE',
    ]);
});

test('a product can be deactivated and activated again from the list', function () {
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($this->owner)
        ->patch(route('admin.products.toggle-active', $product))
        ->assertRedirect()
        ->assertSessionHas('success', 'Producto desactivado.');

    expect($product->fresh()->is_active)->toBeFalse()
        ->and($product->fresh()->trashed())->toBeFalse();

    $this->actingAs($this->owner)
        ->patch(route('admin.products.toggle-active', $product))
        ->assertSessionHas('success', 'Producto activado.');

    expect($product->fresh()->is_active)->toBeTrue();
});

test('toggling a product requires the update permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('products.view');
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($viewer)
        ->patch(route('admin.products.toggle-active', $product))
        ->assertForbidden();

    expect($product->fresh()->is_active)->toBeTrue();
});

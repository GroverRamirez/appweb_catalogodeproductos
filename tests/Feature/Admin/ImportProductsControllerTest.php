<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('propietario');

    $this->vendedor = User::factory()->create();
    $this->vendedor->assignRole('vendedor');
});

// ─── AUTH / PERMISSIONS ───────────────────────────────────────────────────────

test('guest cannot access import endpoint', function () {
    $file = csvFile("codigo,nombre,precio\nABC-001,Producto,10");

    $this->post(route('admin.products.import'), ['file' => $file])
        ->assertRedirect(route('login'));
});

test('vendedor cannot import products (lacks permission)', function () {
    // vendedor has products.update but NOT products.create
    $file = csvFile("codigo,nombre,precio\nABC-001,Producto,10");

    $this->actingAs($this->vendedor)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertForbidden();
});

test('admin can import products', function () {
    $file = csvFile("codigo,nombre,precio\nABC-001,Producto Test,99.99");

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertOk()
        ->assertJsonFragment(['imported' => 1, 'updated' => 0]);
});

// ─── FILE VALIDATION ──────────────────────────────────────────────────────────

test('import rejects request without file', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

test('import rejects non-csv file', function () {
    $file = UploadedFile::fake()->create('productos.pdf', 100, 'application/pdf');

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');
});

// ─── CSV VALIDATION ───────────────────────────────────────────────────────────

test('import returns error when required columns are missing', function () {
    $file = csvFile("nombre,precio\nProducto Sin Codigo,10");

    $response = $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertUnprocessable();

    expect($response->json('message'))->toContain('codigo');
});

test('import rejects entire batch when any row has validation errors', function () {
    // Estrategia todo-o-nada: si CUALQUIER fila es inválida no se importa nada.
    $csv = <<<'CSV'
    codigo,nombre,precio
    ABC-001,Producto Valido,50
    ,Sin Codigo,30
    ABC-003,,25
    CSV;

    $file = csvFile($csv);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertUnprocessable();

    // Ningún producto debe haberse creado, aunque la primera fila era válida
    expect($response->json('imported'))->toBe(0);
    expect($response->json('errors'))->toHaveCount(2);
    expect(Product::where('codigo', 'ABC-001')->exists())->toBeFalse();
});

test('import skips blank rows silently', function () {
    $csv = "codigo,nombre,precio\nABC-001,Producto,50\n\n\n";
    $file = csvFile($csv);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertOk();

    expect($response->json('imported'))->toBe(1);
    expect($response->json('errors'))->toBeEmpty();
});

// ─── IMPORT (CREATE) ──────────────────────────────────────────────────────────

test('import creates product with correct data', function () {
    $file = csvFile("codigo,nombre,precio,stock\nGAMER-001,Laptop Gamer,1299.99,5");

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertOk();

    $this->assertDatabaseHas('productos', [
        'codigo' => 'GAMER-001',
        'nombre' => 'Laptop Gamer',
        'precio' => 1299.99,
        'stock' => 5,
    ]);
});

test('import generates a unique slug for new products', function () {
    $file = csvFile("codigo,nombre,precio\nABC-001,Mi Producto,10");

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertOk();

    $product = Product::where('codigo', 'ABC-001')->first();
    expect($product->slug)->toBe('mi-producto');
});

test('import sets activo=true by default when column is absent', function () {
    $file = csvFile("codigo,nombre,precio\nABC-001,Producto,10");

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file]);

    expect(Product::where('codigo', 'ABC-001')->value('activo'))->toBeTrue();
});

test('import parses activo column truthy values', function () {
    $csv = implode("\n", [
        'codigo,nombre,precio,activo',
        'P-001,Activo1,10,1',
        'P-002,Activo2,10,true',
        'P-003,Activo3,10,si',
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => csvFile($csv)]);

    foreach (['P-001', 'P-002', 'P-003'] as $code) {
        expect(Product::where('codigo', $code)->value('activo'))->toBeTrue($code.' should be active');
    }
});

test('import parses activo column falsy values', function () {
    $csv = implode("\n", [
        'codigo,nombre,precio,activo',
        'P-010,Inactivo1,10,0',
        'P-011,Inactivo2,10,false',
        'P-012,Inactivo3,10,no',
        'P-013,Inactivo4,10,inactivo',
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => csvFile($csv)]);

    foreach (['P-010', 'P-011', 'P-012', 'P-013'] as $code) {
        expect(Product::where('codigo', $code)->value('activo'))->toBeFalse($code.' should be inactive');
    }
});

// ─── UPSERT (UPDATE) ──────────────────────────────────────────────────────────

test('import updates existing product when code already exists', function () {
    Product::factory()->create(['codigo' => 'UPD-001', 'nombre' => 'Nombre Viejo', 'precio' => 10.00]);

    $file = csvFile("codigo,nombre,precio\nUPD-001,Nombre Nuevo,99.00");

    $response = $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertOk();

    expect($response->json('updated'))->toBe(1);
    expect($response->json('imported'))->toBe(0);

    expect(Product::where('codigo', 'UPD-001')->value('nombre'))->toBe('Nombre Nuevo');
    expect((float) Product::where('codigo', 'UPD-001')->value('precio'))->toBe(99.00);
});

test('import mixed creates and updates', function () {
    Product::factory()->create(['codigo' => 'EXIST-001', 'precio' => 10.00]);

    $csv = "codigo,nombre,precio\nEXIST-001,Actualizado,20\nNEW-001,Nuevo Producto,30";
    $file = csvFile($csv);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertOk();

    expect($response->json('imported'))->toBe(1);
    expect($response->json('updated'))->toBe(1);
    expect($response->json('errors'))->toBeEmpty();
});

// ─── CATEGORY & BRAND RESOLUTION ──────────────────────────────────────────────

test('import resolves category by name case-insensitively', function () {
    $cat = Category::factory()->create(['nombre' => 'Electrónica']);

    $file = csvFile("codigo,nombre,precio,categoria\nABC-001,Producto,10,ELECTRÓNICA");

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file]);

    expect(Product::where('codigo', 'ABC-001')->value('categoria_id'))->toBe($cat->id);
});

test('import resolves brand by name case-insensitively', function () {
    $brand = Brand::factory()->create(['nombre' => 'Samsung']);

    $file = csvFile("codigo,nombre,precio,marca\nABC-001,Producto,10,samsung");

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file]);

    expect(Product::where('codigo', 'ABC-001')->value('marca_id'))->toBe($brand->id);
});

test('import rejects batch and reports error for unknown category', function () {
    $file = csvFile("codigo,nombre,precio,categoria\nABC-001,Producto,10,CategoriaQueNoExiste");

    $response = $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertUnprocessable();

    expect($response->json('imported'))->toBe(0);
    expect($response->json('errors'))->toHaveCount(1);
    expect($response->json('errors.0.errors.0'))->toContain('CategoriaQueNoExiste');
    expect(Product::where('codigo', 'ABC-001')->exists())->toBeFalse();
});

test('import rejects batch and reports error for unknown brand', function () {
    $file = csvFile("codigo,nombre,precio,marca\nABC-001,Producto,10,MarcaQueNoExiste");

    $response = $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertUnprocessable();

    expect($response->json('imported'))->toBe(0);
    expect($response->json('errors'))->toHaveCount(1);
    expect($response->json('errors.0.errors.0'))->toContain('MarcaQueNoExiste');
});

test('import processes optional price_oferta column', function () {
    $file = csvFile("codigo,nombre,precio,precio_oferta\nABC-001,Producto,100,80");

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file]);

    expect((float) Product::where('codigo', 'ABC-001')->value('precio_oferta'))->toBe(80.00);
});

test('import stores null precio_oferta when column is empty', function () {
    $file = csvFile("codigo,nombre,precio,precio_oferta\nABC-001,Producto,100,");

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file]);

    expect(Product::where('codigo', 'ABC-001')->value('precio_oferta'))->toBeNull();
});

// ─── RESPONSE STRUCTURE ───────────────────────────────────────────────────────

test('import response has imported, updated and errors keys', function () {
    $file = csvFile("codigo,nombre,precio\nABC-001,Producto,10");

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertOk()
        ->assertJsonStructure(['imported', 'updated', 'errors']);
});

test('import error entries have row, code and errors keys', function () {
    $file = csvFile("codigo,nombre,precio\n,,abc");

    $response = $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => $file])
        ->assertUnprocessable(); // 422 porque hay errores de validación

    $errors = $response->json('errors');
    expect($errors)->not->toBeEmpty();
    expect($errors[0])->toHaveKey('row');
    expect($errors[0])->toHaveKey('code');
    expect($errors[0])->toHaveKey('errors');
});

// ─── TRANSACCIÓN / ROLLBACK ──────────────────────────────────────────────────

test('import wraps all upserts in a single database transaction', function () {
    // Verificar que el import usa una transacción real. RefreshDatabase ya mantiene una
    // transacción externa (nivel 1); si el import abre su propia transacción, el nivel
    // durante el INSERT será mayor a 1. (DB::listen no captura SAVEPOINT/BEGIN de forma
    // fiable entre drivers, por eso medimos el nivel de transacción directamente.)
    $levelDuringInsert = 0;

    Product::creating(function () use (&$levelDuringInsert) {
        $levelDuringInsert = max($levelDuringInsert, DB::transactionLevel());
    });

    $csv = "codigo,nombre,precio\nTX-001,Producto A,10\nTX-002,Producto B,20";

    $this->actingAs($this->admin)
        ->post(route('admin.products.import'), ['file' => csvFile($csv)])
        ->assertOk();

    expect($levelDuringInsert)->toBeGreaterThan(1);
    // Ambos productos deben haberse creado en la misma transacción
    expect(Product::whereIn('codigo', ['TX-001', 'TX-002'])->count())->toBe(2);
});

// ─── HELPER ───────────────────────────────────────────────────────────────────

/**
 * Create a fake UploadedFile from raw CSV content.
 */
function csvFile(string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent('productos.csv', $content);
}

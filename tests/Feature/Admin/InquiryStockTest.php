<?php

use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

/** Crea una consulta pendiente con un item del producto dado. */
function inquiryWith(Product $product, int $qty): Inquiry
{
    $inquiry = Inquiry::create([
        'customer_name' => 'Cliente Prueba',
        'customer_phone' => '70000000',
        'status' => 'pendiente',
        'source' => 'web',
    ]);

    InquiryItem::create([
        'inquiry_id' => $inquiry->id,
        'product_id' => $product->id,
        'product_name_snapshot' => $product->name,
        'product_code_snapshot' => $product->code,
        'quantity' => $qty,
        'unit_price' => 100,
    ]);

    return $inquiry;
}

test('marcar una consulta como vendida descuenta el stock', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $inquiry = inquiryWith($product, 3);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido'])
        ->assertRedirect();

    expect($product->fresh()->stock)->toBe(7);
});

test('revertir una venta devuelve el stock', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $inquiry = inquiryWith($product, 3);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);
    expect($product->fresh()->stock)->toBe(7);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'contactado']);

    expect($product->fresh()->stock)->toBe(10);
});

test('guardar dos veces en vendido no descuenta dos veces', function () {
    // El descuento se dispara en la transicion, no en cada guardado.
    $product = Product::factory()->create(['stock' => 10]);
    $inquiry = inquiryWith($product, 4);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);
    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);

    expect($product->fresh()->stock)->toBe(6);
});

test('un cambio de estado que no involucra vendido no toca el stock', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $inquiry = inquiryWith($product, 3);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'contactado']);

    expect($product->fresh()->stock)->toBe(10);
});

test('vender mas de lo que hay deja el stock negativo y avisa', function () {
    $product = Product::factory()->create(['stock' => 2, 'codigo' => 'COD-NEG']);
    $inquiry = inquiryWith($product, 5);

    $response = $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);

    // No se recorta a 0: el negativo es la senal de que algo no cuadra.
    expect($product->fresh()->stock)->toBe(-3);

    // El aviso viaja por el canal de toast de Inertia (no por ->with('warning'),
    // que no se comparte y por lo tanto nunca se mostraria).
    $toast = session(SessionKey::FLASH_DATA)['toast'] ?? null;

    expect($toast)->not->toBeNull();
    expect($toast['type'])->toBe('warning');
    expect($toast['message'])->toContain('COD-NEG');

    $response->assertRedirect();
});

test('una venta con stock suficiente no dispara aviso', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $inquiry = inquiryWith($product, 2);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);

    expect(session(SessionKey::FLASH_DATA)['toast'] ?? null)->toBeNull();
    expect($product->fresh()->stock)->toBe(8);
});

test('marcar vendida genera una venta enlazada a la consulta', function () {
    // La venta es la unica fuente de verdad de salidas e ingresos: marcar la
    // consulta como vendida la genera, y de ahi sale el descuento de stock.
    $product = Product::factory()->create(['stock' => 10, 'costo' => 35]);
    $inquiry = inquiryWith($product, 3);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);

    $sale = Sale::first();

    expect($sale)->not->toBeNull();
    expect($sale->origin)->toBe('consulta');
    expect($sale->inquiry_id)->toBe($inquiry->id);
    expect($sale->customer_name)->toBe('Cliente Prueba');
    expect($sale->status)->toBe('confirmada');
    expect((float) $sale->total)->toBe(3 * 100.0);
    expect($sale->items)->toHaveCount(1);
    // El costo tambien queda congelado en la linea, igual que en mostrador.
    expect((float) $sale->items->first()->unit_cost)->toBe(35.0);
});

test('guardar dos veces en vendido no genera dos ventas', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $inquiry = inquiryWith($product, 2);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);
    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);

    expect(Sale::count())->toBe(1);
});

test('revertir el estado anula la venta en vez de borrarla', function () {
    // Anular deja el rastro; borrar lo perderia.
    $product = Product::factory()->create(['stock' => 10]);
    $inquiry = inquiryWith($product, 3);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);
    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'cerrado']);

    $sale = Sale::first();

    expect(Sale::count())->toBe(1);
    expect($sale->status)->toBe('anulada');
    expect($sale->voided_by)->toBe($this->owner->id);
    expect($product->fresh()->stock)->toBe(10);
});

test('volver a marcar vendida despues de revertir genera una venta nueva', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $inquiry = inquiryWith($product, 2);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);
    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'contactado']);
    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);

    expect(Sale::count())->toBe(2);
    expect(Sale::where('estado', 'confirmada')->count())->toBe(1);
    expect($product->fresh()->stock)->toBe(8);
    // Correlativo: la venta anulada igual consumio su numero.
    expect(Sale::orderBy('id')->pluck('numero_recibo')->all())->toBe([1, 2]);
});

test('el descuento de la consulta viaja a la venta', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $inquiry = inquiryWith($product, 2);
    $inquiry->update(['discount_amount' => 50]);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido']);

    $sale = Sale::first();

    expect((float) $sale->subtotal)->toBe(200.0);
    expect((float) $sale->discount_amount)->toBe(50.0);
    expect((float) $sale->total)->toBe(150.0);
});

test('una consulta sin productos del catalogo no genera venta', function () {
    $inquiry = Inquiry::create([
        'customer_name' => 'Sin items',
        'customer_phone' => '70000001',
        'status' => 'pendiente',
        'source' => 'web',
    ]);

    $this->actingAs($this->owner)
        ->patch(route('admin.inquiries.update', $inquiry), ['status' => 'vendido'])
        ->assertRedirect();

    expect(Sale::count())->toBe(0);
});

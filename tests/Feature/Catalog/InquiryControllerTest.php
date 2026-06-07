<?php

use App\Models\Coupon;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ─── HELPERS ──────────────────────────────────────────────────────────────────

function validStorePayload(Product $product): array
{
    return [
        'customer_name'  => 'Ana García',
        'customer_phone' => '51987654321',
        'customer_email' => 'ana@example.com',
        'message'        => 'Me interesa este producto.',
        'source'         => 'web',
        'product_id'     => $product->id,
        'quantity'       => 2,
    ];
}

function validCheckoutPayload(array $products): array
{
    return [
        'customer_name'  => 'Carlos López',
        'customer_phone' => '51912345678',
        'customer_email' => 'carlos@example.com',
        'message'        => 'Por favor contactarme.',
        'source'         => 'web',
        'items'          => collect($products)->map(fn ($p) => [
            'product_id' => $p->id,
            'quantity'   => 1,
        ])->values()->all(),
    ];
}

// ─── STORE ────────────────────────────────────────────────────────────────────

test('store creates an inquiry for a single product', function () {
    $product = Product::factory()->create(['activo' => true, 'precio' => 100.00]);

    $this->post(route('public.inquiries.store'), validStorePayload($product))
        ->assertRedirect();

    $this->assertDatabaseHas('consultas', [
        'cliente_nombre'  => 'Ana García',
        'cliente_telefono' => '51987654321',
        'estado'          => 'pendiente',
    ]);
});

test('store creates an inquiry item linked to the product', function () {
    $product = Product::factory()->create(['activo' => true, 'precio' => 80.00]);

    $this->post(route('public.inquiries.store'), validStorePayload($product));

    $inquiry = Inquiry::first();
    expect($inquiry)->not->toBeNull();
    expect(InquiryItem::where('consulta_id', $inquiry->id)
        ->where('producto_id', $product->id)
        ->exists()
    )->toBeTrue();
});

test('store sets estimated total from product price and quantity', function () {
    $product = Product::factory()->create(['activo' => true, 'precio' => 50.00]);

    $payload = validStorePayload($product);
    $payload['quantity'] = 3;

    $this->post(route('public.inquiries.store'), $payload);

    $inquiry = Inquiry::first();
    expect((float) $inquiry->total_estimated)->toBe(150.00);
});

test('store uses sale_price over regular price for total', function () {
    $product = Product::factory()->create([
        'activo'        => true,
        'precio'        => 100.00,
        'precio_oferta' => 75.00,
    ]);

    $this->post(route('public.inquiries.store'), validStorePayload($product));

    $inquiry = Inquiry::first();
    // quantity=2, sale_price=75 → 150
    expect((float) $inquiry->total_estimated)->toBe(150.00);
});

test('store redirects back with success flash', function () {
    $product = Product::factory()->create(['activo' => true]);

    $this->post(route('public.inquiries.store'), validStorePayload($product))
        ->assertRedirect()
        ->assertSessionHas('success');
});

test('store fails validation when customer_name is missing', function () {
    $product = Product::factory()->create(['activo' => true]);

    $payload = validStorePayload($product);
    unset($payload['customer_name']);

    $this->post(route('public.inquiries.store'), $payload)
        ->assertSessionHasErrors('customer_name');
});

test('store fails validation when customer_phone is missing', function () {
    $product = Product::factory()->create(['activo' => true]);

    $payload = validStorePayload($product);
    unset($payload['customer_phone']);

    $this->post(route('public.inquiries.store'), $payload)
        ->assertSessionHasErrors('customer_phone');
});

test('store fails validation when customer_phone format is invalid', function () {
    $product = Product::factory()->create(['activo' => true]);

    $payload = validStorePayload($product);
    $payload['customer_phone'] = 'not-a-phone';

    $this->post(route('public.inquiries.store'), $payload)
        ->assertSessionHasErrors('customer_phone');
});

test('store fails validation when product_id refers to inactive product', function () {
    $product = Product::factory()->create(['activo' => false]);

    $payload = validStorePayload($product);

    $this->post(route('public.inquiries.store'), $payload)
        ->assertSessionHasErrors('product_id');
});

test('store succeeds without a product_id (general inquiry)', function () {
    $this->post(route('public.inquiries.store'), [
        'customer_name'  => 'Pedro Ruiz',
        'customer_phone' => '51900000000',
    ])->assertRedirect();

    expect(Inquiry::count())->toBe(1);
    expect(InquiryItem::count())->toBe(0);
});

// ─── CHECKOUT ─────────────────────────────────────────────────────────────────

test('checkout creates inquiry with multiple items', function () {
    $products = Product::factory()->count(3)->create(['activo' => true, 'precio' => 10.00]);

    $this->post(route('public.checkout'), validCheckoutPayload($products->all()))
        ->assertRedirect();

    expect(Inquiry::count())->toBe(1);
    expect(InquiryItem::count())->toBe(3);
});

test('checkout calculates total from all items', function () {
    $p1 = Product::factory()->create(['activo' => true, 'precio' => 100.00]);
    $p2 = Product::factory()->create(['activo' => true, 'precio' => 200.00]);

    $this->post(route('public.checkout'), [
        'customer_name'  => 'Test',
        'customer_phone' => '51900000000',
        'items' => [
            ['product_id' => $p1->id, 'quantity' => 2],
            ['product_id' => $p2->id, 'quantity' => 1],
        ],
    ]);

    $inquiry = Inquiry::first();
    // 100×2 + 200×1 = 400
    expect((float) $inquiry->total_estimated)->toBe(400.00);
});

test('checkout applies a percentage coupon discount', function () {
    $product = Product::factory()->create(['activo' => true, 'precio' => 200.00]);

    $coupon = Coupon::create([
        'code'        => 'DESCUENTO10',
        'description' => '10% de descuento',
        'type'        => 'percent',
        'value'       => 10,
        'is_active'   => true,
    ]);

    $this->post(route('public.checkout'), [
        'customer_name'  => 'Test',
        'customer_phone' => '51900000000',
        'coupon_code'    => 'DESCUENTO10',
        'items'          => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    $inquiry = Inquiry::first();
    // 200 - 10% = 180
    expect((float) $inquiry->total_estimated)->toBe(180.00);
    expect((float) $inquiry->discount_amount)->toBe(20.00);
    expect($inquiry->coupon_code)->toBe('DESCUENTO10');
});

test('checkout applies a fixed coupon discount', function () {
    $product = Product::factory()->create(['activo' => true, 'precio' => 150.00]);

    Coupon::create([
        'code'      => 'FIJO50',
        'type'      => 'fixed',
        'value'     => 50,
        'is_active' => true,
    ]);

    $this->post(route('public.checkout'), [
        'customer_name'  => 'Test',
        'customer_phone' => '51900000000',
        'coupon_code'    => 'FIJO50',
        'items'          => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    $inquiry = Inquiry::first();
    expect((float) $inquiry->total_estimated)->toBe(100.00);
});

test('checkout ignores expired coupon', function () {
    $product = Product::factory()->create(['activo' => true, 'precio' => 200.00]);

    Coupon::create([
        'code'      => 'VENCIDO',
        'type'      => 'percent',
        'value'     => 20,
        'is_active' => true,
        'ends_at'   => now()->subDay(),
    ]);

    $this->post(route('public.checkout'), [
        'customer_name'  => 'Test',
        'customer_phone' => '51900000000',
        'coupon_code'    => 'VENCIDO',
        'items'          => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    $inquiry = Inquiry::first();
    expect((float) $inquiry->total_estimated)->toBe(200.00);
    expect($inquiry->coupon_code)->toBeNull();
});

test('checkout ignores coupon when subtotal is below min_subtotal', function () {
    $product = Product::factory()->create(['activo' => true, 'precio' => 50.00]);

    Coupon::create([
        'code'         => 'MIN500',
        'type'         => 'fixed',
        'value'        => 30,
        'min_subtotal' => 500,
        'is_active'    => true,
    ]);

    $this->post(route('public.checkout'), [
        'customer_name'  => 'Test',
        'customer_phone' => '51900000000',
        'coupon_code'    => 'MIN500',
        'items'          => [['product_id' => $product->id, 'quantity' => 1]],
    ]);

    $inquiry = Inquiry::first();
    expect((float) $inquiry->total_estimated)->toBe(50.00);
    expect($inquiry->coupon_code)->toBeNull();
});

test('checkout fails validation when items is empty', function () {
    $this->post(route('public.checkout'), [
        'customer_name'  => 'Test',
        'customer_phone' => '51900000000',
        'items'          => [],
    ])->assertSessionHasErrors('items');
});

test('checkout fails validation when item refers to inactive product', function () {
    $inactive = Product::factory()->create(['activo' => false]);

    $this->post(route('public.checkout'), [
        'customer_name'  => 'Test',
        'customer_phone' => '51900000000',
        'items'          => [['product_id' => $inactive->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('items.0.product_id');
});

test('checkout redirects to signed cart.thanks url on success', function () {
    $product = Product::factory()->create(['activo' => true]);

    $response = $this->post(route('public.checkout'), validCheckoutPayload([$product]));

    $response->assertRedirectContains('/carrito/gracias/');
});

// ─── VALIDATE COUPON ──────────────────────────────────────────────────────────

test('validateCoupon returns valid=true for a usable coupon', function () {
    Coupon::create([
        'code'      => 'VALIDO20',
        'type'      => 'percent',
        'value'     => 20,
        'is_active' => true,
    ]);

    $this->postJson(route('public.coupons.validate'), [
        'code'     => 'VALIDO20',
        'subtotal' => 100.00,
    ])->assertOk()->assertJson([
        'valid' => true,
        'code'  => 'VALIDO20',
        'type'  => 'percent',
    ]);
});

test('validateCoupon returns valid=false for nonexistent code', function () {
    $this->postJson(route('public.coupons.validate'), [
        'code'     => 'NOEXISTE',
        'subtotal' => 100.00,
    ])->assertOk()->assertJson(['valid' => false]);
});

test('validateCoupon returns valid=false for expired coupon', function () {
    Coupon::create([
        'code'      => 'EXPIRADO',
        'type'      => 'fixed',
        'value'     => 10,
        'is_active' => true,
        'ends_at'   => now()->subDay(),
    ]);

    $this->postJson(route('public.coupons.validate'), [
        'code'     => 'EXPIRADO',
        'subtotal' => 100.00,
    ])->assertOk()->assertJson(['valid' => false]);
});

test('validateCoupon returns discount amount in response', function () {
    Coupon::create([
        'code'      => 'DESC15',
        'type'      => 'percent',
        'value'     => 15,
        'is_active' => true,
    ]);

    $this->postJson(route('public.coupons.validate'), [
        'code'     => 'DESC15',
        'subtotal' => 200.00,
    ])->assertOk()->assertJsonFragment(['discount' => 30.0]);
});

test('validateCoupon fails validation when code is missing', function () {
    $this->postJson(route('public.coupons.validate'), [
        'subtotal' => 100.00,
    ])->assertUnprocessable()
      ->assertJsonValidationErrors('code');
});

test('validateCoupon fails validation when subtotal is missing', function () {
    $this->postJson(route('public.coupons.validate'), [
        'code' => 'ANY',
    ])->assertUnprocessable()
      ->assertJsonValidationErrors('subtotal');
});

<?php

use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('cart thanks page does not expose inquiries by sequential id', function () {
    $inquiry = Inquiry::create([
        'customer_name' => 'Cliente Privado',
        'customer_phone' => '70000000',
        'customer_email' => 'cliente@example.com',
        'source' => 'web',
        'status' => 'pendiente',
    ]);

    $this->get('/carrito/gracias?id='.$inquiry->id)
        ->assertNotFound();
});

test('cart thanks page requires a valid signed URL', function () {
    $inquiry = Inquiry::create([
        'customer_name' => 'Cliente Privado',
        'customer_phone' => '70000000',
        'customer_email' => 'cliente@example.com',
        'source' => 'web',
        'status' => 'pendiente',
    ]);

    $this->get('/carrito/gracias/'.$inquiry->public_token)
        ->assertForbidden();
});

test('cart thanks page only exposes the public order summary', function () {
    $inquiry = Inquiry::create([
        'customer_name' => 'Cliente Privado',
        'customer_phone' => '70000000',
        'customer_email' => 'cliente@example.com',
        'source' => 'web',
        'status' => 'pendiente',
        'total_estimated' => 150,
    ]);

    InquiryItem::create([
        'inquiry_id' => $inquiry->id,
        'product_name_snapshot' => 'Producto de prueba',
        'product_code_snapshot' => 'SKU-1',
        'quantity' => 2,
        'unit_price' => 75,
    ]);

    $this->get(URL::signedRoute('cart.thanks', ['inquiry' => $inquiry->public_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/CartThanks')
            ->where('inquiry.id', $inquiry->id)
            ->has('inquiry.items', 1)
            ->missing('inquiry.customer_name')
            ->missing('inquiry.customer_phone')
            ->missing('inquiry.customer_email')
        );
});

test('checkout redirects to a signed tokenized thanks URL', function () {
    $product = Product::factory()->create([
        'price' => 50,
        'sale_price' => null,
    ]);

    $response = $this->post(route('public.checkout'), [
        'customer_name' => 'Cliente Privado',
        'customer_phone' => '70000000',
        'customer_email' => 'cliente@example.com',
        'source' => 'web',
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 1,
            ],
        ],
    ]);

    $location = $response->headers->get('Location');

    $response->assertRedirect();
    expect($location)->toContain('/carrito/gracias/');
    expect($location)->toContain('signature=');
    expect($location)->not->toContain('id=');
});

test('checkout rejects inactive products', function () {
    $product = Product::factory()->create([
        'is_active' => false,
    ]);

    $this
        ->from('/carrito')
        ->post(route('public.checkout'), [
            'customer_name' => 'Cliente Privado',
            'customer_phone' => '70000000',
            'customer_email' => 'cliente@example.com',
            'source' => 'web',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertRedirect('/carrito')
        ->assertSessionHasErrors('items.0.product_id');
});

test('product inquiry rejects inactive products', function () {
    $product = Product::factory()->create([
        'is_active' => false,
    ]);

    $this
        ->from('/catalogo')
        ->post(route('public.inquiries.store'), [
            'customer_name' => 'Cliente Privado',
            'customer_phone' => '70000000',
            'customer_email' => 'cliente@example.com',
            'source' => 'web',
            'product_id' => $product->id,
            'quantity' => 1,
        ])
        ->assertRedirect('/catalogo')
        ->assertSessionHasErrors('product_id');
});

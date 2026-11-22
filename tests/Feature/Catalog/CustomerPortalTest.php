<?php

use App\Models\Category;
use App\Models\Client;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\WishlistItem;
use App\Support\AuthRedirect;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function createCompleteClient(): array
{
    $user = User::factory()->create();
    $user->assignRole('cliente');

    static $phoneCounter = 71110000;
    $phoneCounter++;

    $client = Client::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'id_card' => '7654321 LP',
        'phone' => (string) $phoneCounter,
    ]);

    return [$user, $client];
}

test('un invitado es redirigido al login al intentar ver mis pedidos', function () {
    $this->get(route('customer.orders'))
        ->assertRedirect(route('login'));
});

test('un cliente sin CI ni telefono es redirigido a completar datos', function () {
    $user = User::factory()->create();
    $user->assignRole('cliente');

    $this->actingAs($user)
        ->get(route('customer.orders'))
        ->assertRedirect(route('client-profile.edit'));
});

test('un cliente con perfil completo puede ver el portal de pedidos', function () {
    [$user, $client] = createCompleteClient();

    $inquiry = Inquiry::create([
        'client_id' => $client->id,
        'customer_name' => $client->name,
        'customer_phone' => $client->phone,
        'status' => 'pendiente',
        'total_estimated' => 450.00,
    ]);

    InquiryItem::create([
        'inquiry_id' => $inquiry->id,
        'product_name_snapshot' => 'Monitor 24"',
        'product_code_snapshot' => 'MON-24',
        'quantity' => 1,
        'unit_price' => 450.00,
    ]);

    $sale = Sale::create([
        'receipt_number' => 101,
        'client_id' => $client->id,
        'customer_name' => $client->name,
        'subtotal' => 450.00,
        'discount_amount' => 0.00,
        'total' => 450.00,
        'payment_method' => 'efectivo',
        'status' => 'confirmada',
        'origin' => 'mostrador',
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_name_snapshot' => 'Monitor 24"',
        'product_code_snapshot' => 'MON-24',
        'quantity' => 1,
        'unit_price' => 450.00,
    ]);

    $response = $this->actingAs($user)
        ->get(route('customer.orders'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalog/CustomerPortal')
            ->has('inquiries', 1)
            ->has('sales', 1)
            ->where('client.id_card', '7654321 LP')
        );
});

test('un cliente no ve los pedidos ni compras de otros clientes', function () {
    [$user1, $client1] = createCompleteClient();
    [$user2, $client2] = createCompleteClient();

    Inquiry::create([
        'client_id' => $client2->id,
        'customer_name' => $client2->name,
        'customer_phone' => $client2->phone,
        'status' => 'pendiente',
        'total_estimated' => 200.00,
    ]);

    $response = $this->actingAs($user1)
        ->get(route('customer.orders'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalog/CustomerPortal')
            ->has('inquiries', 0)
            ->has('sales', 0)
        );
});

test('un cliente puede descargar el comprobante en PDF de su compra', function () {
    [$user, $client] = createCompleteClient();

    $sale = Sale::create([
        'receipt_number' => 202,
        'client_id' => $client->id,
        'customer_name' => $client->name,
        'subtotal' => 300.00,
        'total' => 300.00,
        'payment_method' => 'qr',
        'status' => 'confirmada',
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_name_snapshot' => 'Teclado Mecánico',
        'product_code_snapshot' => 'TEC-01',
        'quantity' => 1,
        'unit_price' => 300.00,
    ]);

    $response = $this->actingAs($user)
        ->get(route('customer.sales.receipt', $sale));

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('un cliente no puede descargar el comprobante de compra de otro cliente', function () {
    [$user1, $client1] = createCompleteClient();
    [$user2, $client2] = createCompleteClient();

    $sale2 = Sale::create([
        'receipt_number' => 303,
        'client_id' => $client2->id,
        'customer_name' => $client2->name,
        'subtotal' => 500.00,
        'total' => 500.00,
        'payment_method' => 'efectivo',
        'status' => 'confirmada',
    ]);

    $this->actingAs($user1)
        ->get(route('customer.sales.receipt', $sale2))
        ->assertForbidden();
});

test('el staff puede descargar cualquier comprobante de venta', function () {
    [$userClient, $client] = createCompleteClient();

    $staff = User::factory()->create();
    $staff->assignRole('propietario');

    $sale = Sale::create([
        'receipt_number' => 404,
        'client_id' => $client->id,
        'customer_name' => $client->name,
        'subtotal' => 150.00,
        'total' => 150.00,
        'payment_method' => 'efectivo',
        'status' => 'confirmada',
    ]);

    $this->actingAs($staff)
        ->get(route('customer.sales.receipt', $sale))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('un cliente puede sincronizar favoritos desde el frontend hacia la base de datos', function () {
    [$user, $client] = createCompleteClient();

    $category = Category::factory()->create();

    $product1 = Product::create([
        'name' => 'Procesador Ryzen 5',
        'code' => 'CPU-R5',
        'price' => 180.00,
        'category_id' => $category->id,
        'stock' => 10,
    ]);

    $product2 = Product::create([
        'name' => 'SSD 1TB NVMe',
        'code' => 'SSD-1TB',
        'price' => 75.00,
        'category_id' => $category->id,
        'stock' => 15,
    ]);

    $response = $this->actingAs($user)
        ->postJson(route('customer.wishlist.sync'), [
            'product_ids' => [$product1->id, $product2->id],
        ]);

    $response->assertOk()
        ->assertJsonCount(2, 'items');

    expect(WishlistItem::where('user_id', $user->id)->count())->toBe(2);
});

test('un cliente puede alternar un producto en favoritos con toggle', function () {
    [$user, $client] = createCompleteClient();

    $category = Category::factory()->create();

    $product = Product::create([
        'name' => 'Mouse Gamer RGB',
        'code' => 'MOU-RGB',
        'price' => 45.00,
        'category_id' => $category->id,
        'stock' => 20,
    ]);

    // Primer toggle: se agrega
    $res1 = $this->actingAs($user)
        ->postJson(route('customer.wishlist.toggle', $product));

    $res1->assertOk()
        ->assertJson([
            'in_wishlist' => true,
            'count' => 1,
        ]);

    expect(WishlistItem::where('user_id', $user->id)->where('producto_id', $product->id)->exists())->toBeTrue();

    // Segundo toggle: se quita
    $res2 = $this->actingAs($user)
        ->postJson(route('customer.wishlist.toggle', $product));

    $res2->assertOk()
        ->assertJson([
            'in_wishlist' => false,
            'count' => 0,
        ]);

    expect(WishlistItem::where('user_id', $user->id)->where('producto_id', $product->id)->exists())->toBeFalse();
});

test('AuthRedirect envia a un cliente al home publico', function () {
    [$user, $client] = createCompleteClient();

    expect(AuthRedirect::for($user))->toBe('/');
});

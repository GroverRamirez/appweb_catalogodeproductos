<?php

use App\Models\Client;
use App\Models\Inquiry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
});

function createTestClient(): array
{
    $user = User::factory()->create();
    $user->assignRole('cliente');

    static $phoneCounter = 72220000;
    $phoneCounter++;

    $client = Client::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'id_card' => '1234567 SC',
        'phone' => (string) $phoneCounter,
    ]);

    return [$user, $client];
}

test('la pantalla de confirmacion de pedido muestra datos de pago y estado', function () {
    $inquiry = Inquiry::create([
        'customer_name' => 'Comprador Web',
        'customer_phone' => '+59178888888',
        'status' => 'pendiente',
        'total_estimated' => 350.00,
        'payment_status' => 'sin_pago',
    ]);

    $url = URL::signedRoute('cart.thanks', ['inquiry' => $inquiry->public_token]);

    $response = $this->get($url);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalog/CartThanks')
            ->has('inquiry', fn ($inq) => $inq
                ->where('id', $inquiry->id)
                ->where('public_token', $inquiry->public_token)
                ->where('payment_status', 'sin_pago')
                ->etc()
            )
        );
});

test('un cliente puede subir su comprobante de pago mediante el token publico', function () {
    $inquiry = Inquiry::create([
        'customer_name' => 'Comprador Web',
        'customer_phone' => '+59178888888',
        'status' => 'pendiente',
        'total_estimated' => 350.00,
        'payment_status' => 'sin_pago',
    ]);

    $file = UploadedFile::fake()->image('comprobante_banco.jpg', 600, 800);

    $response = $this->post(route('public.inquiries.receipt', ['inquiry' => $inquiry->public_token]), [
        'receipt' => $file,
    ]);

    $response->assertRedirect();
    $inquiry->refresh();

    expect($inquiry->payment_status)->toBe('comprobante_subido');
    expect($inquiry->payment_receipt_path)->not->toBeNull();
    Storage::disk('public')->assertExists($inquiry->payment_receipt_path);

    $this->assertDatabaseHas('consulta_notas', [
        'consulta_id' => $inquiry->id,
        'cuerpo' => 'El cliente subió el comprobante de pago.',
    ]);
});

test('la subida de comprobante valida tipo y tamanio de archivo', function () {
    $inquiry = Inquiry::create([
        'customer_name' => 'Comprador Web',
        'customer_phone' => '+59178888888',
        'status' => 'pendiente',
        'total_estimated' => 350.00,
    ]);

    // Archivo no permitido (.txt)
    $badFile = UploadedFile::fake()->create('archivo.txt', 10, 'text/plain');
    $response = $this->post(route('public.inquiries.receipt', ['inquiry' => $inquiry->public_token]), [
        'receipt' => $badFile,
    ]);
    $response->assertSessionHasErrors('receipt');

    // Archivo demasiado grande (> 5MB)
    $largeFile = UploadedFile::fake()->create('enorme.pdf', 6000, 'application/pdf');
    $responseLarge = $this->post(route('public.inquiries.receipt', ['inquiry' => $inquiry->public_token]), [
        'receipt' => $largeFile,
    ]);
    $responseLarge->assertSessionHasErrors('receipt');
});

test('un cliente autenticado puede subir su comprobante desde el portal mis pedidos', function () {
    [$user, $client] = createTestClient();

    $inquiry = Inquiry::create([
        'client_id' => $client->id,
        'customer_name' => $client->name,
        'customer_phone' => $client->phone,
        'status' => 'pendiente',
        'total_estimated' => 500.00,
        'payment_status' => 'sin_pago',
    ]);

    $pdfFile = UploadedFile::fake()->create('recibo_transferencia.pdf', 200, 'application/pdf');

    $response = $this->actingAs($user)
        ->post(route('customer.orders.receipt', $inquiry), [
            'receipt' => $pdfFile,
        ]);

    $response->assertRedirect();
    $inquiry->refresh();

    expect($inquiry->payment_status)->toBe('comprobante_subido');
    expect($inquiry->payment_receipt_path)->not->toBeNull();
    Storage::disk('public')->assertExists($inquiry->payment_receipt_path);
});

test('un cliente no puede subir comprobante a un pedido ajeno', function () {
    [$userA, $clientA] = createTestClient();
    [$userB, $clientB] = createTestClient();

    $inquiryA = Inquiry::create([
        'client_id' => $clientA->id,
        'customer_name' => $clientA->name,
        'customer_phone' => $clientA->phone,
        'status' => 'pendiente',
        'total_estimated' => 100.00,
    ]);

    $file = UploadedFile::fake()->image('recibo.png');

    $response = $this->actingAs($userB)
        ->post(route('customer.orders.receipt', $inquiryA), [
            'receipt' => $file,
        ]);

    $response->assertForbidden();
});

test('el staff administrativo puede verificar un comprobante de pago', function () {
    $admin = User::factory()->create();
    $admin->assignRole('propietario');

    $inquiry = Inquiry::create([
        'customer_name' => 'Cliente A',
        'customer_phone' => '+59170001122',
        'status' => 'pendiente',
        'payment_status' => 'comprobante_subido',
        'payment_receipt_path' => 'comprobantes_pago/fake.jpg',
    ]);

    $response = $this->actingAs($admin)
        ->patch(route('admin.inquiries.payment-status', $inquiry), [
            'payment_status' => 'verificado',
            'notes_pago' => 'Monto verificado en cuenta BCP.',
        ]);

    $response->assertRedirect();
    $inquiry->refresh();

    expect($inquiry->payment_status)->toBe('verificado');
    expect($inquiry->payment_verified_at)->not->toBeNull();
    expect($inquiry->payment_verified_by)->toBe($admin->id);
    expect($inquiry->payment_notes)->toBe('Monto verificado en cuenta BCP.');

    $this->assertDatabaseHas('consulta_notas', [
        'consulta_id' => $inquiry->id,
        'user_id' => $admin->id,
    ]);
});

test('el staff administrativo puede rechazar un comprobante con motivo', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $inquiry = Inquiry::create([
        'customer_name' => 'Cliente B',
        'customer_phone' => '+59170003344',
        'status' => 'pendiente',
        'payment_status' => 'comprobante_subido',
        'payment_receipt_path' => 'comprobantes_pago/fake.jpg',
    ]);

    $response = $this->actingAs($vendedor)
        ->patch(route('admin.inquiries.payment-status', $inquiry), [
            'payment_status' => 'rechazado',
            'notes_pago' => 'Comprobante borroso y sin número de transacción visible.',
        ]);

    $response->assertRedirect();
    $inquiry->refresh();

    expect($inquiry->payment_status)->toBe('rechazado');
    expect($inquiry->payment_verified_at)->toBeNull();
    expect($inquiry->payment_verified_by)->toBeNull();
    expect($inquiry->payment_notes)->toBe('Comprobante borroso y sin número de transacción visible.');
});

test('el filtro de payment_status en el listado de consultas funciona', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole('vendedor');

    $inqSinPago = Inquiry::create([
        'customer_name' => 'Sin Pago',
        'customer_phone' => '+59171111111',
        'status' => 'pendiente',
        'payment_status' => 'sin_pago',
    ]);

    $inqConPago = Inquiry::create([
        'customer_name' => 'Con Pago',
        'customer_phone' => '+59172222222',
        'status' => 'pendiente',
        'payment_status' => 'comprobante_subido',
    ]);

    $response = $this->actingAs($vendedor)
        ->get(route('admin.inquiries.index', ['payment_status' => 'comprobante_subido']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/inquiries/Index')
            ->has('inquiries.data', 1)
            ->where('inquiries.data.0.id', $inqConPago->id)
        );
});

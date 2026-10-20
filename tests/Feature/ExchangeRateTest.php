<?php

use App\Models\Inquiry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExchangeRate;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** Respuesta tipica de la API del BCB. */
function bcbPayload(float $rate = 12.02, string $date = '2026-09-29'): array
{
    return [
        'tc_oficial' => [
            'regimen' => 'flexible',
            'valor' => $rate,
            'compra' => $rate,
            'venta' => $rate,
            'moneda' => 'USD/BOB',
            'fecha' => $date,
        ],
    ];
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->rates = app(ExchangeRate::class);
    $this->rates->store(12.02, 'bcb', '2026-09-29');
});

test('the daily command stores the rate published by the central bank', function () {
    Http::fake(['*' => Http::response(bcbPayload(12.35, '2026-10-01'))]);

    $this->artisan('tipo-cambio:actualizar')->assertSuccessful();

    expect(app(ExchangeRate::class)->current())->toBe(12.35)
        ->and(app(ExchangeRate::class)->source())->toBe('bcb');
});

test('a failed lookup leaves the last known rate in place', function () {
    // Sin red, la tienda tiene que seguir vendiendo con la ultima cotizacion:
    // una vitrina en cero seria peor que una cotizacion de ayer.
    Http::fake(['*' => Http::response('', 500)]);

    $this->artisan('tipo-cambio:actualizar')->assertFailed();

    expect(app(ExchangeRate::class)->current())->toBe(12.02);
});

test('an absurd rate from the api is rejected', function () {
    Http::fake(['*' => Http::response(bcbPayload(0.0))]);

    $this->artisan('tipo-cambio:actualizar')->assertFailed();

    expect(app(ExchangeRate::class)->current())->toBe(12.02);
});

test('the rate can be fixed by hand from the command', function () {
    $this->artisan('tipo-cambio:actualizar', ['--valor' => '12,50'])->assertSuccessful();

    expect(app(ExchangeRate::class)->current())->toBe(12.5)
        ->and(app(ExchangeRate::class)->source())->toBe('manual');
});

test('the public catalog publishes prices in bolivianos', function () {
    Product::factory()->create(['precio' => 100, 'precio_oferta' => null, 'activo' => true]);

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('products.data.0.price', '1202.00')
            ->where('products.data.0.price_usd', fn ($usd) => (float) $usd === 100.0)
        );
});

test('the price filter is read in bolivianos', function () {
    Product::factory()->create(['nombre' => 'Barato', 'precio' => 50, 'precio_oferta' => null]);
    Product::factory()->create(['nombre' => 'Caro', 'precio' => 200, 'precio_oferta' => null]);

    // 1000 Bs son unos 83 USD: entra el de 50, queda afuera el de 200.
    $this->get(route('catalog.index', ['max_price' => 1000]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Barato')
        );
});

test('the checkout freezes the rate it quoted', function () {
    $product = Product::factory()->create(['precio' => 100, 'precio_oferta' => null]);

    $this->post(route('public.checkout'), [
        'customer_name' => 'Cliente Demo',
        'customer_phone' => '70000000',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ])->assertRedirect();

    $inquiry = Inquiry::query()->latest('id')->firstOrFail();

    expect((float) $inquiry->exchange_rate)->toBe(12.02)
        ->and((float) $inquiry->total_estimated)->toBe(2404.0)
        ->and((float) $inquiry->items()->first()->unit_price)->toBe(1202.0);
});

test('a counter sale freezes the rate and the cost in bolivianos', function () {
    $owner = User::factory()->create();
    $owner->assignRole('propietario');

    $product = Product::factory()->create(['precio' => 100, 'costo' => 60, 'stock' => 10]);

    $this->actingAs($owner)->post(route('admin.sales.store'), [
        'customer_name' => 'Cliente Mostrador',
        'customer_phone' => '70000001',
        'payment_method' => 'efectivo',
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            // El formulario ya trabaja en bolivianos.
            'unit_price' => 1202,
        ]],
    ])->assertRedirect();

    $sale = Sale::query()->latest('id')->firstOrFail();
    $item = $sale->items()->firstOrFail();

    expect((float) $sale->exchange_rate)->toBe(12.02)
        ->and((float) $sale->total)->toBe(1202.0)
        // 60 USD al cambio de la venta: el margen se calcula entre bolivianos.
        ->and((float) $item->unit_cost)->toBe(721.2);
});

test('editing the rate in settings marks it as manual', function () {
    $owner = User::factory()->create();
    $owner->assignRole('propietario');

    $this->actingAs($owner)->patch(route('admin.settings.update'), [
        'settings' => [
            ['key' => 'exchange_rate', 'value' => '13.10'],
        ],
    ])->assertRedirect();

    expect(app(ExchangeRate::class)->current())->toBe(13.1)
        ->and(app(ExchangeRate::class)->source())->toBe('manual')
        ->and(Setting::get('exchange_rate_updated_at'))->not->toBeEmpty();
});

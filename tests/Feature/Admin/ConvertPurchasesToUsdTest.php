<?php

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\ExchangeRate;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(ExchangeRate::class)->store(10.0);
});

/** Compra con una linea, como quedaron las registradas en bolivianos. */
function bsPurchase(string $createdAt, float $unitCost, int $qty, ?float $previous = null): Purchase
{
    $purchase = Purchase::factory()->create(['costo_total' => $unitCost * $qty]);
    $purchase->forceFill(['created_at' => Carbon::parse($createdAt)])->save();

    PurchaseItem::create([
        'purchase_id' => $purchase->id,
        'product_id' => Product::factory()->create()->id,
        'product_name_snapshot' => 'Camara',
        'product_code_snapshot' => 'CAM-1',
        'quantity' => $qty,
        'unit_cost' => $unitCost,
        'previous_cost' => $previous,
    ]);

    return $purchase;
}

test('the dry run lists the purchases and writes nothing', function () {
    $purchase = bsPurchase('2026-09-08', 490, 3);

    $this->artisan('compras:convertir-a-usd', ['--antes' => '2026-10-06', '--dry-run' => true])
        ->expectsOutputToContain('Simulacion: se convertirian 1 compras y 1 lineas')
        ->assertSuccessful();

    expect((float) $purchase->fresh()->total_cost)->toBe(1470.0);
});

test('it converts unit cost, previous cost and total of purchases before the date', function () {
    $old = bsPurchase('2026-09-08', 490, 3, 550);
    $new = bsPurchase('2026-10-07', 40, 2);

    $this->artisan('compras:convertir-a-usd', ['--antes' => '2026-10-06', '--sin-backup' => true])
        ->expectsConfirmation('Se van a convertir 1 compras y 1 lineas. Continuar?', 'yes')
        ->assertSuccessful();

    $item = $old->items()->first();
    expect((float) $item->unit_cost)->toBe(49.0)
        ->and((float) $item->previous_cost)->toBe(55.0)
        ->and((float) $old->fresh()->total_cost)->toBe(147.0)
        // La posterior ya estaba en dolares y no se toca.
        ->and((float) $new->fresh()->total_cost)->toBe(80.0)
        ->and((float) $new->items()->first()->unit_cost)->toBe(40.0)
        ->and(Setting::get('purchases_currency'))->toBe('USD')
        ->and(Setting::get('price_list_currency'))->toBe('USD');
});

test('it refuses to convert twice', function () {
    Setting::set('purchases_currency', 'USD', 'string', 'moneda');
    $purchase = bsPurchase('2026-09-08', 490, 3);

    $this->artisan('compras:convertir-a-usd', ['--antes' => '2026-10-06', '--sin-backup' => true])
        ->assertFailed();

    expect((float) $purchase->fresh()->total_cost)->toBe(1470.0);
});

test('it requires the cutoff date', function () {
    $this->artisan('compras:convertir-a-usd', ['--dry-run' => true])->assertFailed();
});

test('it aborts without writing when the backup cannot be made', function () {
    // Los tests corren en SQLite: la copia desde la app solo funciona en MySQL.
    $purchase = bsPurchase('2026-09-08', 490, 3);

    $this->artisan('compras:convertir-a-usd', ['--antes' => '2026-10-06'])
        ->expectsConfirmation('Se van a convertir 1 compras y 1 lineas. Continuar?', 'yes')
        ->assertFailed();

    expect((float) $purchase->fresh()->total_cost)->toBe(1470.0);
});

test('converting the price list also converts the purchases', function () {
    DB::table('configuraciones')->where('clave', 'price_list_currency')->update(['valor' => 'BOB']);
    Setting::set('price_list_currency', 'BOB', 'string', 'moneda');
    $purchase = bsPurchase('2026-09-08', 490, 3);

    $this->artisan('precios:convertir-a-usd')
        ->expectsConfirmation('Se van a convertir 1 productos. Hiciste backup de la base?', 'yes')
        ->assertSuccessful();

    expect((float) $purchase->fresh()->total_cost)->toBe(147.0);
});

test('the dashboard shows purchase totals in bolivianos', function () {
    $this->seed(RoleSeeder::class);
    $owner = User::factory()->create();
    $owner->assignRole('propietario');

    Purchase::factory()->create(['estado' => 'confirmada', 'costo_total' => 100]);

    $this->actingAs($owner)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.purchases_spend_30d', fn ($v) => (float) $v === 1000.0)
            ->where('spendByMonth.0.total', fn ($v) => (float) $v === 1000.0)
            ->where('recentPurchases.0.total_cost', fn ($v) => (float) $v === 1000.0)
            ->etc()
        );
});

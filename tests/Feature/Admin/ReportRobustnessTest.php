<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\SalesReport;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');

    $this->today = CarbonImmutable::now();
});

function reportLine(Sale $sale, Product $product, array $overrides = []): SaleItem
{
    return SaleItem::create(array_merge([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'product_name_snapshot' => $product->name,
        'product_code_snapshot' => $product->code,
        'quantity' => 1,
        'unit_price' => 100,
        'unit_cost' => 60,
    ], $overrides));
}

test('an unreadable date falls back to the default range instead of failing', function () {
    $this->actingAs($this->owner)
        ->get(route('admin.reports.index', ['from' => 'abc', 'to' => '2026-02-31']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('range.to', $this->today->format('Y-m-d'))
            ->where('range.from', $this->today->subDays(29)->format('Y-m-d'))
        );
});

test('a reversed range is turned around', function () {
    $this->actingAs($this->owner)
        ->get(route('admin.reports.index', ['from' => '2026-10-10', 'to' => '2026-10-01']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('range.from', '2026-10-01')
            ->where('range.to', '2026-10-10')
            ->has('series', 10)
        );
});

test('a mistyped year does not build a series of centuries', function () {
    // Un "desde" con el año mal escrito (0202) armaba cientos de miles de
    // filas y colgaba el servidor; ahora se recorta al maximo permitido.
    $this->actingAs($this->owner)
        ->get(route('admin.reports.index', ['from' => '0202-01-01', 'to' => '2026-10-01']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('range.to', '2026-10-01')
            ->has('series', SalesReport::MAX_DAYS)
        );

    $this->actingAs($this->owner)
        ->get(route('admin.reports.export', ['from' => '0202-01-01', 'to' => '2026-10-01']))
        ->assertOk();
});

test('lines without a cost stay out of the margin and are reported', function () {
    $sale = Sale::factory()->create([
        'numero_recibo' => 1,
        'estado' => 'confirmada',
        'total' => 200,
        'descuento_monto' => 0,
        'created_at' => $this->today->setTime(10, 0),
    ]);

    reportLine($sale, Product::factory()->create(), ['unit_cost' => 60]);
    // Sin costo cargado: antes contaba como costo cero y sumaba 100 de "ganancia".
    reportLine($sale, Product::factory()->create(), ['unit_cost' => null]);

    $this->actingAs($this->owner)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kpis.margin', fn ($margin) => (float) $margin === 40.0)
            ->where('kpis.lines_without_cost', 1)
        );
});

test('a sale of a product without cost freezes a null cost, not zero', function () {
    $product = Product::factory()->create(['costo' => null, 'precio' => 10, 'stock' => 5, 'activo' => true]);

    $this->actingAs($this->owner)
        ->post(route('admin.sales.store'), [
            'payment_method' => 'efectivo',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 70]],
        ])
        ->assertSessionHasNoErrors();

    expect(SaleItem::sole()->unit_cost)->toBeNull();
});

test('a renamed product is ranked once, under its current name', function () {
    $product = Product::factory()->create(['nombre' => 'Mouse inalambrico', 'codigo' => 'MOU-1']);
    $sale = Sale::factory()->create([
        'numero_recibo' => 1,
        'estado' => 'confirmada',
        'created_at' => $this->today->setTime(10, 0),
    ]);

    reportLine($sale, $product, ['product_name_snapshot' => 'Mouse viejo', 'quantity' => 2]);
    reportLine($sale, $product, ['product_name_snapshot' => 'Mouse inalambrico', 'quantity' => 3]);

    $this->actingAs($this->owner)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('topSold', 1)
            ->where('topSold.0.name', 'Mouse inalambrico')
            ->where('topSold.0.qty', 5)
        );
});

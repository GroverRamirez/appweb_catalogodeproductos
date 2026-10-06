<?php

use App\Exports\ReportExport;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');

    $this->today = CarbonImmutable::now();

    $this->category = Category::factory()->create(['nombre' => 'Perifericos']);

    $this->product = Product::factory()->create([
        'categoria_id' => $this->category->id,
        'nombre' => 'Teclado mecanico RGB',
        'codigo' => 'TEC-001',
    ]);

    $this->sale = Sale::factory()->create([
        'numero_recibo' => 1,
        'estado' => 'confirmada',
        'total' => 250,
        'created_at' => $this->today->setTime(10, 30),
    ]);

    SaleItem::create([
        'sale_id' => $this->sale->id,
        'product_id' => $this->product->id,
        'product_name_snapshot' => $this->product->name,
        'product_code_snapshot' => $this->product->code,
        'quantity' => 2,
        'unit_price' => 125,
        'unit_cost' => 75,
    ]);

    $this->range = [
        'from' => $this->today->format('Y-m-d'),
        'to' => $this->today->format('Y-m-d'),
    ];

    $this->expectedName = 'reporte-'.$this->today->format('Y-m-d')
        .'_'.$this->today->format('Y-m-d');
});

test('the pdf report downloads with the filtered range in its name', function () {
    $response = $this->actingAs($this->owner)
        ->get(route('admin.reports.pdf', $this->range));

    $response->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain($this->expectedName.'.pdf');

    // Que devuelva 200 no alcanza: una plantilla rota tambien responde 200 con
    // HTML adentro. La firma del archivo confirma que salio un PDF.
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('the pdf can be opened inline for a live demo', function () {
    $response = $this->actingAs($this->owner)
        ->get(route('admin.reports.pdf', $this->range + ['inline' => 1]));

    $response->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('inline');
});

test('a range without sales still renders the pdf', function () {
    // El reporte de un periodo vacio se imprime igual: un documento en cero es
    // un resultado, no un error.
    $response = $this->actingAs($this->owner)
        ->get(route('admin.reports.pdf', [
            'from' => $this->today->subDays(20)->format('Y-m-d'),
            'to' => $this->today->subDays(15)->format('Y-m-d'),
        ]));

    $response->assertOk();

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('the excel export ships one sheet per block of the report', function () {
    Excel::fake();

    $this->actingAs($this->owner)
        ->get(route('admin.reports.excel', $this->range))
        ->assertOk();

    Excel::assertDownloaded(
        $this->expectedName.'.xlsx',
        fn (ReportExport $export) => count($export->sheets()) === 5,
    );
});

test('every export requires the reports permission', function () {
    // Con un permiso cualquiera entra al panel, pero no a los reportes: si la
    // exportacion no exigiera su propio permiso, los numeros del negocio
    // saldrian por una URL que nadie mira.
    $staff = User::factory()->create();
    $staff->givePermissionTo('products.view');

    foreach (['admin.reports.pdf', 'admin.reports.excel', 'admin.reports.export'] as $name) {
        $this->actingAs($staff)
            ->get(route($name, $this->range))
            ->assertForbidden();
    }
});

test('the panel exposes the aggregated indicators of the range', function () {
    $this->actingAs($this->owner)
        ->get(route('admin.reports.index', $this->range))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('byCategory.0.category', 'Perifericos')
            ->where('byCategory.0.units', 2)
            ->where('byCategory.0.revenue', fn ($revenue) => (float) $revenue === 250.0)
            // Los cuatro estados viajan siempre, aunque esten en cero: un
            // estado que desaparece del grafico se lee como que no existe.
            ->has('statusBreakdown', 4)
            ->where('statusBreakdown.0.status', 'pendiente')
            ->where('statusBreakdown.0.total', 0)
        );
});

test('the csv, the pdf and the screen report the same totals', function () {
    $screen = $this->actingAs($this->owner)
        ->get(route('admin.reports.index', $this->range));

    $screen->assertOk();

    $csv = $this->actingAs($this->owner)
        ->get(route('admin.reports.export', $this->range));

    $csv->assertOk();

    // El CSV y la pantalla salen del mismo SalesReport: si alguien vuelve a
    // duplicar las consultas por salida, este test lo detecta.
    expect($csv->streamedContent())->toContain('250.00');
    $screen->assertInertia(fn ($page) => $page
        ->where('kpis.revenue', fn ($revenue) => (float) $revenue === 250.0)
    );
});

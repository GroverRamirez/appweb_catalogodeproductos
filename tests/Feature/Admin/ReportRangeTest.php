<?php

use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');

    $this->today = CarbonImmutable::now();

    $this->sale = Sale::factory()->create([
        'numero_recibo' => 1,
        'estado' => 'confirmada',
        'total' => 250,
        'created_at' => $this->today->setTime(21, 42),
    ]);
});

test('a single day range includes what happened that day', function () {
    // `to` llega como fecha sin hora: si no se estira al final del día, el
    // rango termina a las 00:00 y todo el día queda afuera.
    $this->actingAs($this->owner)
        ->get(route('admin.reports.index', [
            'from' => $this->today->format('Y-m-d'),
            'to' => $this->today->format('Y-m-d'),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kpis.sales_count', 1)
            ->where('kpis.revenue', fn ($revenue) => (float) $revenue === 250.0)
        );
});

test('a range ending the day before excludes it', function () {
    $this->actingAs($this->owner)
        ->get(route('admin.reports.index', [
            'from' => $this->today->subDays(5)->format('Y-m-d'),
            'to' => $this->today->subDay()->format('Y-m-d'),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('kpis.sales_count', 0));
});

test('the default range covers today', function () {
    $this->actingAs($this->owner)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('kpis.sales_count', 1));
});

test('only from given still reaches the end of today', function () {
    // Pasar solo un extremo no puede colapsar el rango: con fechas mutables,
    // calcular uno a partir del otro lo movía y ambos terminaban iguales.
    $this->actingAs($this->owner)
        ->get(route('admin.reports.index', ['from' => $this->today->subDays(3)->format('Y-m-d')]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('range.to', $this->today->format('Y-m-d'))
            ->where('kpis.sales_count', 1)
        );
});

test('the csv export covers the same range as the screen', function () {
    $response = $this->actingAs($this->owner)
        ->get(route('admin.reports.export', [
            'from' => $this->today->format('Y-m-d'),
            'to' => $this->today->format('Y-m-d'),
        ]));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain($this->today->format('Y-m-d'));
    expect($csv)->toContain('250.00');
});

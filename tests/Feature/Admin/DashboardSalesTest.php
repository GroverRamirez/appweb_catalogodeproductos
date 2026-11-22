<?php

use App\Models\Client;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

test('dashboard includes salesByMonth and recentSales', function () {
    $client = Client::factory()->create(['name' => 'María Pérez']);

    $sale1 = Sale::factory()->create([
        'client_id' => $client->id,
        'customer_name' => 'María Pérez',
        'status' => 'confirmada',
        'total' => 250.00,
        'created_at' => now(),
    ]);

    $sale2 = Sale::factory()->create([
        'status' => 'anulada',
        'total' => 500.00,
        'created_at' => now(),
    ]);

    $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Dashboard')
            ->has('salesByMonth')
            ->has('recentSales')
            ->has('recentSales.0', fn (Assert $s) => $s
                ->where('id', $sale2->id)
                ->where('status', 'anulada')
                ->etc()
            )
            ->has('recentSales.1', fn (Assert $s) => $s
                ->where('id', $sale1->id)
                ->where('customer_name', 'María Pérez')
                ->where('status', 'confirmada')
                ->etc()
            )
            ->etc()
        );
});

test('salesByMonth only sums confirmed sales within the last 6 months', function () {
    Sale::factory()->create([
        'status' => 'confirmada',
        'total' => 300.00,
        'created_at' => now(),
    ]);

    Sale::factory()->create([
        'status' => 'anulada',
        'total' => 400.00,
        'created_at' => now(),
    ]);

    $response = $this->actingAs($this->owner)
        ->get(route('admin.dashboard'))
        ->assertOk();

    $salesByMonth = $response->viewData('page')['props']['salesByMonth'];
    $currentMonth = now()->format('Y-m');

    $thisMonthEntry = collect($salesByMonth)->firstWhere('month', $currentMonth);

    expect($thisMonthEntry)->not->toBeNull();
    expect((float) $thisMonthEntry['total'])->toBe(300.00);
});

<?php

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

test('sale show page provides qrSvg for thermal receipt printing', function () {
    $sale = Sale::factory()->create([
        'receipt_number' => 45,
        'customer_name' => 'Carlos Mendoza',
        'status' => 'confirmada',
        'total' => 180.50,
    ]);

    $this->actingAs($this->owner)
        ->get(route('admin.sales.show', $sale))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/sales/Show')
            ->where('sale.id', $sale->id)
            ->where('sale.receipt_number', 45)
            ->has('qrSvg')
            ->where('qrSvg', fn ($val) => is_string($val) && str_contains($val, '<svg') && str_contains($val, 'xmlns'))
            ->etc()
        );
});

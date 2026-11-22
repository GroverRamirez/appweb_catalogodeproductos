<?php

use App\Models\Purchase;
use App\Models\Supplier;
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

test('purchase show page provides qrSvg for thermal ticket printing', function () {
    $supplier = Supplier::factory()->create(['nombre' => 'Importadora Andina']);
    $purchase = Purchase::factory()->create([
        'proveedor_id' => $supplier->id,
        'numero_referencia' => 'REM-9921',
        'estado' => 'confirmada',
        'costo_total' => 450.00,
    ]);

    $this->actingAs($this->owner)
        ->get(route('admin.purchases.show', $purchase))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/purchases/Show')
            ->where('purchase.id', $purchase->id)
            ->has('qrSvg')
            ->where('qrSvg', fn ($val) => is_string($val) && str_contains($val, '<svg') && str_contains($val, 'xmlns'))
            ->etc()
        );
});

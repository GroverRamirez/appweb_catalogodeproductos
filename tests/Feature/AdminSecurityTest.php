<?php

use App\Models\Inquiry;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('seller cannot delete products without delete permission', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');
    $product = Product::factory()->create();

    $this->actingAs($seller)
        ->delete(route('admin.products.destroy', $product))
        ->assertForbidden();

    $this->assertModelExists($product);
});

test('seller cannot delete inquiries without delete permission', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');
    $inquiry = Inquiry::create([
        'customer_name' => 'Cliente Privado',
        'customer_phone' => '70000000',
        'source' => 'web',
        'status' => 'pendiente',
    ]);

    $this->actingAs($seller)
        ->delete(route('admin.inquiries.destroy', $inquiry))
        ->assertForbidden();

    $this->assertModelExists($inquiry);
});

test('seller cannot update catalog settings without settings permission', function () {
    $seller = User::factory()->create();
    $seller->assignRole('vendedor');
    $setting = Setting::create([
        'key' => 'store_name',
        'value' => 'Original',
        'group' => 'general',
        'label' => 'Store name',
        'type' => 'string',
    ]);

    $this->actingAs($seller)
        ->patch(route('admin.settings.update'), [
            'settings' => [
                [
                    'key' => 'store_name',
                    'value' => 'Changed',
                ],
            ],
        ])
        ->assertForbidden();

    expect($setting->fresh()->value)->toBe('Original');
});

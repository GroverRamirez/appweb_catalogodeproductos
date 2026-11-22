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

test('products cannot be deleted, only deactivated', function () {
    $owner = User::factory()->create();
    $owner->assignRole('propietario');
    $product = Product::factory()->create(['is_active' => true]);

    $this->actingAs($owner)
        ->delete("/admin/products/{$product->id}")
        ->assertStatus(405);

    $this->assertModelExists($product);
    expect($product->fresh()->trashed())->toBeFalse();
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

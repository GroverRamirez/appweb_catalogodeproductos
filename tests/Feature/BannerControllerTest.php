<?php

use App\Models\Banner;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->owner = User::factory()->create();
    $this->owner->assignRole('propietario');
});

test('owner can create a banner with an uploaded image', function () {
    Storage::fake('public');

    $image = UploadedFile::fake()->image('hero.jpg', 1600, 600);

    $this->actingAs($this->owner)
        ->post(route('admin.banners.store'), [
            'title' => 'Promociones de temporada',
            'subtitle' => 'Nuevos productos disponibles',
            'image_file' => $image,
            'link' => '/catalogo',
            'cta_text' => 'Ver catalogo',
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.banners.index'));

    $banner = Banner::query()->first();

    expect($banner)->not->toBeNull()
        ->and($banner->title)->toBe('Promociones de temporada')
        ->and($banner->image)->toStartWith('banners/')
        ->and($banner->image)->toEndWith('.webp');

    Storage::disk('public')->assertExists($banner->image);
});

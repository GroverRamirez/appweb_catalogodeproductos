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

test('banner links only accept https or internal paths', function () {
    Storage::fake('public');

    foreach (['javascript:alert(1)', '//otro.sitio/x', 'data:text/html,hola'] as $link) {
        $this->actingAs($this->owner)
            ->post(route('admin.banners.store'), [
                'image_file' => UploadedFile::fake()->image('hero.jpg', 1600, 600),
                'link' => $link,
            ])
            ->assertSessionHasErrors('link');
    }

    foreach (['/catalogo?categoria=redes', 'https://wa.me/59170000000'] as $link) {
        $this->actingAs($this->owner)
            ->post(route('admin.banners.store'), [
                'image_file' => UploadedFile::fake()->image('hero.jpg', 1600, 600),
                'link' => $link,
            ])
            ->assertSessionHasNoErrors();
    }

    expect(Banner::count())->toBe(2);
});

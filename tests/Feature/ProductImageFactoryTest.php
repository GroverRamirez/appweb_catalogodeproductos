<?php

use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses product photography urls for generated product images', function () {
    $image = ProductImage::factory()->create();

    expect(str_starts_with($image->path, 'https://images.unsplash.com/'))->toBeTrue()
        ->and($image->path)->not->toContain('picsum.photos')
        ->and($image->path)->not->toContain('products/demo/');
});

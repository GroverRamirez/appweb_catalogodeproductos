<?php

use App\Models\Banner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses product photography urls for generated banners', function () {
    $banner = Banner::factory()->create();

    expect(str_starts_with($banner->image, 'https://images.unsplash.com/'))->toBeTrue()
        ->and($banner->image)->not->toContain('picsum.photos');
});

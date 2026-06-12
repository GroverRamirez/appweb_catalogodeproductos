<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('wishlist page renders for guests', function () {
    $this->get(route('wishlist.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('catalog/Wishlist'));
});

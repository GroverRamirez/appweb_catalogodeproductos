<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the privacy policy is public', function () {
    $this->get('/privacidad')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Legal')
            ->where('page', 'privacidad'));
});

test('the terms of use are public', function () {
    $this->get('/terminos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('catalog/Legal')
            ->where('page', 'terminos'));
});

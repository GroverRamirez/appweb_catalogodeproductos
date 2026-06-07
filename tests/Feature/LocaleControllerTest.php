<?php

it('sets the selected locale cookie and redirects back', function () {
    $response = $this
        ->from('/')
        ->get('/locale/es');

    $response
        ->assertRedirect('/')
        ->assertPlainCookie('locale', 'es');
});

it('falls back to spanish for unsupported locales', function () {
    $response = $this
        ->from('/')
        ->get('/locale/fr');

    $response
        ->assertRedirect('/')
        ->assertPlainCookie('locale', 'es');
});

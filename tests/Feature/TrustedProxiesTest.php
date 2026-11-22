<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('behind a trusted proxy the forwarded https and client ip are honored', function () {
    config(['trustedproxy.proxies' => ['10.0.0.1']]);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.5',
        ])
        ->get('/')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security');

    expect(request()->ip())->toBe('203.0.113.5');
});

test('forwarded headers from an untrusted source are ignored', function () {
    config(['trustedproxy.proxies' => ['10.0.0.1']]);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.5',
        ])
        ->get('/')
        ->assertOk()
        ->assertHeaderMissing('Strict-Transport-Security');

    expect(request()->ip())->toBe('198.51.100.7');
});

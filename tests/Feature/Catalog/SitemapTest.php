<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('sitemap.xml lista inicio, catalogo y solo productos y categorias activos', function () {
    $categoria = Category::factory()->create();
    $categoriaInactiva = Category::factory()->inactive()->create();
    $activo = Product::factory()->create();
    $inactivo = Product::factory()->create(['activo' => false]);

    $response = $this->get('/sitemap.xml')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/xml');

    $xml = $response->getContent();

    expect($xml)
        ->toContain('<loc>'.url('/').'</loc>')
        ->toContain('<loc>'.url('/catalogo').'</loc>')
        ->toContain(url('/catalogo/'.$activo->slug))
        ->not->toContain(url('/catalogo/'.$inactivo->slug).'<')
        ->toContain('category='.$categoria->slug)
        ->not->toContain('category='.$categoriaInactiva->slug.'<');

    // Debe ser XML valido
    expect(simplexml_load_string($xml))->not->toBeFalse();
});

test('robots.txt bloquea el panel y apunta al sitemap con URL absoluta', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /settings')
        ->toMatch('#^Sitemap: https://\S+/sitemap\.xml$#m');
});

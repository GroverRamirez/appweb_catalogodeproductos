<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function bulkPng(): string
{
    $im = imagecreatetruecolor(40, 40);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 120, 60));
    ob_start();
    imagepng($im);
    $bytes = (string) ob_get_clean();
    imagedestroy($im);

    return $bytes;
}

beforeEach(function () {
    Storage::fake('public');
});

test('importa imágenes en lote desde un CSV', function () {
    Http::fake(['*' => Http::response(bulkPng(), 200, ['Content-Type' => 'image/png'])]);

    $a = Product::factory()->create();
    $b = Product::factory()->create();

    $csv = "codigo,url\n{$a->code},https://x/a.jpg\n{$b->code},https://x/b.jpg\n";
    $path = storage_path('app/test_imagenes.csv');
    file_put_contents($path, $csv);

    $this->artisan('products:images', ['file' => $path])->assertExitCode(0);

    expect($a->images()->count())->toBe(1);
    expect($b->images()->count())->toBe(1);

    @unlink($path);
});

test('salta filas con producto inexistente pero procesa las válidas', function () {
    Http::fake(['*' => Http::response(bulkPng(), 200, ['Content-Type' => 'image/png'])]);

    $a = Product::factory()->create();

    $csv = "codigo,url\nNO-EXISTE,https://x/x.jpg\n{$a->code},https://x/a.jpg\n";
    $path = storage_path('app/test_imagenes2.csv');
    file_put_contents($path, $csv);

    $this->artisan('products:images', ['file' => $path])->assertExitCode(0);

    expect($a->images()->count())->toBe(1);

    @unlink($path);
});

test('con --replace reemplaza las imágenes previas del producto', function () {
    Http::fake(['*' => Http::response(bulkPng(), 200, ['Content-Type' => 'image/png'])]);

    $a = Product::factory()->create();

    $path = storage_path('app/test_imagenes3.csv');
    file_put_contents($path, "codigo,url\n{$a->code},https://x/a.jpg\n");

    $this->artisan('products:images', ['file' => $path])->assertExitCode(0);
    $this->artisan('products:images', ['file' => $path, '--replace' => true])->assertExitCode(0);

    expect($a->images()->count())->toBe(1);

    @unlink($path);
});

test('falla si el archivo no existe', function () {
    $this->artisan('products:images', ['file' => 'no/existe.csv'])->assertExitCode(1);
});

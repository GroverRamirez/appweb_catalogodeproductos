<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// PNG real generado con GD para que Intervention lo decodifique sin problemas.
function fakePngBytes(): string
{
    $im = imagecreatetruecolor(40, 40);
    imagefill($im, 0, 0, imagecolorallocate($im, 80, 140, 200));
    ob_start();
    imagepng($im);
    $bytes = (string) ob_get_clean();
    imagedestroy($im);

    return $bytes;
}

beforeEach(function () {
    Storage::fake('public');
});

test('descarga una imagen desde URL y la adjunta como principal', function () {
    Http::fake(['*' => Http::response(fakePngBytes(), 200, ['Content-Type' => 'image/png'])]);

    $product = Product::factory()->create();

    $this->artisan('products:image', [
        'code' => $product->code,
        'url' => 'https://example.com/foto.jpg',
    ])->assertExitCode(0);

    expect($product->images()->count())->toBe(1);

    $img = $product->images()->first();
    expect($img->is_main)->toBeTrue();
    expect($img->path)->toEndWith('.webp');
    Storage::disk('public')->assertExists($img->path);
    Storage::disk('public')->assertExists($img->thumb_path);
});

test('la segunda imagen no se marca como principal', function () {
    Http::fake(['*' => Http::response(fakePngBytes(), 200, ['Content-Type' => 'image/png'])]);

    $product = Product::factory()->create();

    $this->artisan('products:image', ['code' => $product->code, 'url' => 'https://x/1.jpg'])->assertExitCode(0);
    $this->artisan('products:image', ['code' => $product->code, 'url' => 'https://x/2.jpg'])->assertExitCode(0);

    expect($product->images()->count())->toBe(2);
    expect($product->images()->where('principal', true)->count())->toBe(1);
});

test('falla si el recurso no es una imagen', function () {
    Http::fake(['*' => Http::response('<html>no soy imagen</html>', 200, ['Content-Type' => 'text/html'])]);

    $product = Product::factory()->create();

    $this->artisan('products:image', ['code' => $product->code, 'url' => 'https://x/pagina.html'])
        ->assertExitCode(1);

    expect($product->images()->count())->toBe(0);
});

test('falla si el producto no existe', function () {
    $this->artisan('products:image', ['code' => 'NO-EXISTE', 'url' => 'https://x/f.jpg'])
        ->assertExitCode(1);
});

test('rechaza URLs que no son http/https', function () {
    $product = Product::factory()->create();

    $this->artisan('products:image', ['code' => $product->code, 'url' => 'file:///etc/passwd'])
        ->assertExitCode(1);

    expect($product->images()->count())->toBe(0);
});

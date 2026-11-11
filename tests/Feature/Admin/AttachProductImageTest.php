<?php

use App\Models\Product;
use App\Services\ImageProcessor;
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
    // Los hosts de prueba no existen: se resuelven a una IP publica fija.
    ImageProcessor::resolveHostsUsing(fn () => ['93.184.216.34']);
});

afterEach(function () {
    ImageProcessor::resolveHostsUsing(null);
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

test('rechaza un host que resuelve a una IP interna', function () {
    ImageProcessor::resolveHostsUsing(fn () => ['10.0.0.5']);
    Http::fake(['*' => Http::response(fakePngBytes(), 200, ['Content-Type' => 'image/png'])]);
    $product = Product::factory()->create();

    $this->artisan('products:image', ['code' => $product->code, 'url' => 'https://interno.example/f.png'])
        ->assertExitCode(1);

    Http::assertNothingSent();
});

test('rechaza un host con IPv6 de loopback', function () {
    ImageProcessor::resolveHostsUsing(fn () => ['93.184.216.34', '::1']);
    Http::fake();
    $product = Product::factory()->create();

    $this->artisan('products:image', ['code' => $product->code, 'url' => 'https://doble.example/f.png'])
        ->assertExitCode(1);

    Http::assertNothingSent();
});

test('no sigue una redireccion hacia la red interna', function () {
    // El primer salto es publico y valido; la redireccion apunta a localhost.
    Http::fake([
        'https://publico.example/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secreto.png']),
        '*' => Http::response(fakePngBytes(), 200, ['Content-Type' => 'image/png']),
    ]);
    $product = Product::factory()->create();

    $this->artisan('products:image', ['code' => $product->code, 'url' => 'https://publico.example/f.png'])
        ->assertExitCode(1);

    expect($product->images()->count())->toBe(0);
    Http::assertSentCount(1);
});

test('sigue una redireccion publica valida', function () {
    Http::fake([
        'https://viejo.example/nueva/*' => Http::response(fakePngBytes(), 200, ['Content-Type' => 'image/png']),
        'https://viejo.example/*' => Http::response('', 301, ['Location' => '/nueva/f.png']),
    ]);
    $product = Product::factory()->create();

    $this->artisan('products:image', ['code' => $product->code, 'url' => 'https://viejo.example/f.png'])
        ->assertExitCode(0);

    expect($product->images()->count())->toBe(1);
});

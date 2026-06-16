<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageProcessor;
use Illuminate\Console\Command;

class AttachProductImage extends Command
{
    /**
     * Adjunta una imagen a un producto descargándola desde una URL de internet.
     * La imagen se procesa a WebP (principal + thumbnail) y se guarda local.
     *
     * Uso:
     *   php artisan products:image P-CODIGO https://.../foto.jpg
     *   php artisan products:image P-CODIGO https://.../foto.jpg --alt="Texto alt"
     */
    protected $signature = 'products:image
        {code : Código del producto}
        {url : URL de la imagen en internet}
        {--alt= : Texto alternativo (por defecto, el nombre del producto)}';

    protected $description = 'Descarga una imagen desde una URL y la adjunta a un producto (WebP local)';

    public function handle(ImageProcessor $images): int
    {
        $product = Product::where('codigo', $this->argument('code'))->first();

        if (! $product) {
            $this->error("Producto con código «{$this->argument('code')}» no encontrado.");

            return self::FAILURE;
        }

        try {
            ['path' => $path, 'thumb_path' => $thumb] = $images->productFromUrl(
                $this->argument('url'),
                $product->id,
            );
        } catch (\Throwable $e) {
            $this->error("No se pudo cargar la imagen: {$e->getMessage()}");

            return self::FAILURE;
        }

        $count = $product->images()->count();

        ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'thumb_path' => $thumb,
            'alt' => $this->option('alt') ?: $product->name,
            'sort_order' => $count,
            'is_main' => $count === 0,
        ]);

        $this->info("Imagen adjuntada a «{$product->name}» ({$product->code}).");

        return self::SUCCESS;
    }
}

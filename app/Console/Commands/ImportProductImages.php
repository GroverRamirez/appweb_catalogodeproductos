<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageProcessor;
use Illuminate\Console\Command;

class ImportProductImages extends Command
{
    /**
     * Adjunta imágenes en lote desde un CSV con columnas: codigo,url[,alt]
     * Cada imagen se descarga de internet y se guarda local en WebP.
     *
     * Es best-effort: una fila con error no detiene el resto; al final muestra
     * un resumen. Por defecto agrega; con --replace borra las imágenes previas
     * del producto antes de adjuntar la nueva.
     *
     * Uso:
     *   php artisan products:images storage/app/imagenes.csv
     *   php artisan products:images storage/app/imagenes.csv --replace
     */
    protected $signature = 'products:images
        {file : Ruta al CSV con columnas codigo,url[,alt]}
        {--replace : Borrar las imágenes existentes del producto antes de adjuntar}';

    protected $description = 'Importa imágenes de productos en lote desde un CSV de codigo,url';

    public function handle(ImageProcessor $images): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->error("No se encontró el archivo: {$file}");

            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        $headers = array_map(fn ($h) => mb_strtolower(trim($h)), fgetcsv($handle) ?: []);

        if (! in_array('codigo', $headers, true) || ! in_array('url', $headers, true)) {
            fclose($handle);
            $this->error('El CSV debe tener al menos las columnas: codigo, url');

            return self::FAILURE;
        }

        $ok = 0;
        $fail = 0;
        $row = 1;

        while (($cols = fgetcsv($handle)) !== false) {
            $row++;
            if (count(array_filter($cols, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach ($headers as $i => $h) {
                $data[$h] = isset($cols[$i]) ? trim($cols[$i]) : '';
            }

            $codigo = $data['codigo'];
            $url = $data['url'];

            $product = Product::where('codigo', $codigo)->first();
            if (! $product) {
                $this->warn("Fila {$row}: producto «{$codigo}» no encontrado.");
                $fail++;

                continue;
            }

            try {
                ['path' => $path, 'thumb_path' => $thumb] = $images->productFromUrl($url, $product->id);
            } catch (\Throwable $e) {
                $this->warn("Fila {$row} ({$codigo}): {$e->getMessage()}");
                $fail++;

                continue;
            }

            if ($this->option('replace')) {
                foreach ($product->images as $old) {
                    $images->delete($old->path ?? '', $old->thumb_path);
                    $old->delete();
                }
            }

            $count = $product->images()->count();
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'thumb_path' => $thumb,
                'alt' => ($data['alt'] ?? '') !== '' ? $data['alt'] : $product->name,
                'sort_order' => $count,
                'is_main' => $count === 0,
            ]);

            $this->line("  <info>✓</info> {$codigo} — {$product->name}");
            $ok++;
        }

        fclose($handle);

        $this->newLine();
        $this->info("Listo: {$ok} imágenes adjuntadas, {$fail} con error.");

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}

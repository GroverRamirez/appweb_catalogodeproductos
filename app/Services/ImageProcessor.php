<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Procesa imágenes subidas antes de persistirlas:
 *  - Convierte a WebP (mejor compresión, soporte universal desde 2023)
 *  - Redimensiona sin distorsión (contain)
 *  - Genera thumbnail con recorte centrado (cover)
 *  - Elimina metadatos EXIF (privacidad)
 *
 * Uso:
 *   $result = app(ImageProcessor::class)->product($file, $productId);
 *   // $result['path']       → ruta principal en storage/public
 *   // $result['thumb_path'] → ruta thumbnail en storage/public
 */
class ImageProcessor
{
    // ── Tamaños para cada contexto ─────────────────────────────────────────────

    /** Producto: imagen principal (detalle) */
    private const PRODUCT_MAX_W  = 1200;
    private const PRODUCT_MAX_H  = 1200;
    private const PRODUCT_THUMB  = 400;    // px, recorte cuadrado
    private const PRODUCT_Q      = 85;
    private const PRODUCT_THUMB_Q = 80;

    /** Banner: imagen de hero/slider */
    private const BANNER_MAX_W   = 1920;
    private const BANNER_MAX_H   = 800;
    private const BANNER_Q       = 82;

    /** Settings: logo, favicon, imágenes de configuración */
    private const SETTINGS_MAX_W = 800;
    private const SETTINGS_MAX_H = 800;
    private const SETTINGS_Q     = 85;

    // ──────────────────────────────────────────────────────────────────────────

    private ImageManager $manager;

    public function __construct()
    {
        // GD es el driver incluido en PHP por defecto.
        // Si el entorno tiene Imagick instalado puedes cambiar a:
        //   new \Intervention\Image\Drivers\Imagick\Driver()
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Procesa una imagen de producto.
     *
     * @return array{path: string, thumb_path: string}
     */
    public function product(UploadedFile $file, int|string $productId): array
    {
        $uuid  = Str::uuid()->toString();
        $dir   = "products/{$productId}";
        $path  = "{$dir}/{$uuid}.webp";
        $thumb = "{$dir}/{$uuid}_thumb.webp";

        // Imagen principal: resize contain (sin recortar) → WebP
        $main = $this->manager->read($file->getRealPath());
        $main->scaleDown(self::PRODUCT_MAX_W, self::PRODUCT_MAX_H);
        Storage::disk('public')->put($path, $main->toWebp(self::PRODUCT_Q));

        // Thumbnail: recorte cuadrado centrado → WebP
        $th = $this->manager->read($file->getRealPath());
        $th->cover(self::PRODUCT_THUMB, self::PRODUCT_THUMB);
        Storage::disk('public')->put($thumb, $th->toWebp(self::PRODUCT_THUMB_Q));

        return ['path' => $path, 'thumb_path' => $thumb];
    }

    /**
     * Procesa una imagen de banner (sin thumbnail).
     *
     * @return string path en el disco 'public'
     */
    public function banner(UploadedFile $file): string
    {
        $path = 'banners/'.Str::uuid().'.webp';

        $img = $this->manager->read($file->getRealPath());
        $img->scaleDown(self::BANNER_MAX_W, self::BANNER_MAX_H);
        Storage::disk('public')->put($path, $img->toWebp(self::BANNER_Q));

        return $path;
    }

    /**
     * Procesa una imagen de configuración (logo, favicon, etc.) sin thumbnail.
     *
     * @return string path en el disco 'public'
     */
    public function setting(UploadedFile $file, string $subdirectory = 'settings'): string
    {
        $path = "{$subdirectory}/".Str::uuid().'.webp';

        $img = $this->manager->read($file->getRealPath());
        $img->scaleDown(self::SETTINGS_MAX_W, self::SETTINGS_MAX_H);
        Storage::disk('public')->put($path, $img->toWebp(self::SETTINGS_Q));

        return $path;
    }

    /**
     * Elimina del disco una imagen y su thumbnail asociado (si existe).
     * Seguro para llamar con paths HTTP externos (no hace nada).
     */
    public function delete(string $path, ?string $thumbPath = null, string $disk = 'public'): void
    {
        if (! str_starts_with($path, 'http')) {
            Storage::disk($disk)->delete($path);
        }

        if ($thumbPath && ! str_starts_with($thumbPath, 'http')) {
            Storage::disk($disk)->delete($thumbPath);
        }
    }
}

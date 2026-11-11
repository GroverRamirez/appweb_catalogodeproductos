<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Psr\Http\Message\StreamInterface;

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
    private const PRODUCT_MAX_W = 1200;

    private const PRODUCT_MAX_H = 1200;

    private const PRODUCT_THUMB = 400;    // px, recorte cuadrado

    private const PRODUCT_Q = 85;

    private const PRODUCT_THUMB_Q = 80;

    /** Banner: imagen de hero/slider */
    private const BANNER_MAX_W = 1920;

    private const BANNER_MAX_H = 800;

    private const BANNER_Q = 82;

    /** Settings: logo, favicon, imágenes de configuración */
    private const SETTINGS_MAX_W = 800;

    private const SETTINGS_MAX_H = 800;

    private const SETTINGS_Q = 85;

    // ──────────────────────────────────────────────────────────────────────────

    private ImageManager $manager;

    public function __construct()
    {
        // GD es el driver incluido en PHP por defecto.
        // Si el entorno tiene Imagick instalado puedes cambiar a:
        //   new \Intervention\Image\Drivers\Imagick\Driver()
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Procesa una imagen de producto.
     *
     * @return array{path: string, thumb_path: string}
     */
    public function product(UploadedFile $file, int|string $productId): array
    {
        return $this->storeProductImage($file->getRealPath(), $productId);
    }

    /**
     * Descarga una imagen desde una URL de internet, la procesa (WebP + thumbnail)
     * y la guarda local. Pensado para poblar el catálogo con fotos remotas.
     *
     * @return array{path: string, thumb_path: string}
     */
    public function productFromUrl(string $url, int|string $productId): array
    {
        $tmp = $this->downloadToTemp($url);

        try {
            return $this->storeProductImage($tmp, $productId);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Procesa una imagen de producto desde un path local (subida o descargada):
     * principal (resize contain) + thumbnail (recorte cuadrado), ambas WebP.
     *
     * @return array{path: string, thumb_path: string}
     */
    private function storeProductImage(string $sourcePath, int|string $productId): array
    {
        $uuid = Str::uuid()->toString();
        $dir = "products/{$productId}";
        $path = "{$dir}/{$uuid}.webp";
        $thumb = "{$dir}/{$uuid}_thumb.webp";

        // Imagen principal: resize contain (sin recortar) → WebP
        $main = $this->manager->decodePath($sourcePath);
        $main->scaleDown(self::PRODUCT_MAX_W, self::PRODUCT_MAX_H);
        Storage::disk('public')->put($path, (string) $main->encode(new WebpEncoder(self::PRODUCT_Q)));

        // Thumbnail: recorte cuadrado centrado → WebP
        $th = $this->manager->decodePath($sourcePath);
        $th->cover(self::PRODUCT_THUMB, self::PRODUCT_THUMB);
        Storage::disk('public')->put($thumb, (string) $th->encode(new WebpEncoder(self::PRODUCT_THUMB_Q)));

        return ['path' => $path, 'thumb_path' => $thumb];
    }

    /** Tamaño máximo de una imagen descargada por URL. */
    private const DOWNLOAD_MAX_BYTES = 12 * 1024 * 1024;

    /** Saltos de redirección permitidos; cada uno se vuelve a validar. */
    private const DOWNLOAD_MAX_REDIRECTS = 3;

    /** Resolución DNS reemplazable en tests (los hosts de prueba no existen). */
    private static ?\Closure $hostResolver = null;

    /**
     * Reemplaza cómo se resuelven los hosts (solo para tests).
     *
     * @param  (callable(string): array<int, string>)|null  $resolver
     */
    public static function resolveHostsUsing(?callable $resolver): void
    {
        self::$hostResolver = $resolver ? \Closure::fromCallable($resolver) : null;
    }

    /**
     * Descarga una URL a un archivo temporal con validaciones de seguridad:
     * solo http/https, bloquea direcciones internas (SSRF), exige Content-Type
     * de imagen y limita el tamaño. Devuelve el path temporal (a borrar por el caller).
     *
     * Contra SSRF: cada salto (la URL y cada redirección) se valida por
     * separado, IPv4 e IPv6, y la conexión se fija con CURLOPT_RESOLVE a la IP
     * ya validada, para que un DNS que cambie entre la validación y la
     * descarga (DNS rebinding) no la desvíe a la red interna.
     */
    private function downloadToTemp(string $url): string
    {
        for ($hop = 0; $hop <= self::DOWNLOAD_MAX_REDIRECTS; $hop++) {
            [$host, $port, $ip] = $this->assertPublicUrl($url);
            $pinned = str_contains($ip, ':') ? "[{$ip}]" : $ip;

            $response = Http::timeout(20)
                ->withoutRedirecting()
                ->withOptions([
                    'stream' => true,
                    'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinned}"]],
                ])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; CatalogoBot/1.0)'])
                ->get($url);

            if ($response->redirect()) {
                $location = (string) $response->header('Location');
                if ($location === '') {
                    throw new \RuntimeException('La URL redirige sin indicar a dónde.');
                }
                $url = $this->absoluteUrl($url, $location);

                continue;
            }

            if (! $response->successful()) {
                throw new \RuntimeException("La descarga falló (HTTP {$response->status()}).");
            }

            $contentType = (string) $response->header('Content-Type');
            if (! str_starts_with($contentType, 'image/')) {
                throw new \RuntimeException("El recurso no es una imagen (Content-Type: {$contentType}).");
            }

            if ((int) $response->header('Content-Length') > self::DOWNLOAD_MAX_BYTES) {
                throw new \RuntimeException('La imagen supera el tamaño máximo permitido (12 MB).');
            }

            return $this->streamToTemp($response->toPsrResponse()->getBody());
        }

        throw new \RuntimeException('La URL redirige demasiadas veces.');
    }

    /**
     * Valida esquema y host, y devuelve [host, puerto, IP pública validada].
     *
     * @return array{0: string, 1: int, 2: string}
     */
    private function assertPublicUrl(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('La URL debe usar http o https.');
        }

        $host = trim((string) ($parts['host'] ?? ''), '[]');
        if ($host === '') {
            throw new \InvalidArgumentException('La URL no tiene un host válido.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveHost($host);
        if ($ips === []) {
            throw new \InvalidArgumentException('No se pudo resolver el host de la URL.');
        }

        // FILTER_FLAG_GLOBAL_RANGE (PHP 8.2+) descarta también CGNAT,
        // documentación y demás rangos no ruteables, además de privadas y
        // reservadas.
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE)) {
                throw new \InvalidArgumentException('No se permiten direcciones internas.');
            }
        }

        return [$host, $port, $ips[0]];
    }

    /**
     * IPs (v4 y v6) a las que resuelve un host.
     *
     * @return array<int, string>
     */
    private function resolveHost(string $host): array
    {
        if (self::$hostResolver) {
            return array_values((self::$hostResolver)($host));
        }

        $ips = @gethostbynamel($host) ?: [];
        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /** Resuelve el Location de una redirección contra la URL que lo envió. */
    private function absoluteUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';

        if (str_starts_with($location, '//')) {
            return "{$scheme}:{$location}";
        }

        $origin = $scheme.'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $dir = rtrim(dirname($parts['path'] ?? '/'), '/\\');

        return $origin.$dir.'/'.$location;
    }

    /**
     * Copia el cuerpo a un archivo temporal cortando apenas supera el límite,
     * sin cargarlo entero en memoria (el Content-Length puede faltar o mentir).
     */
    private function streamToTemp(StreamInterface $body): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'prodimg_');
        $out = fopen($tmp, 'wb');
        $total = 0;

        if ($body->isSeekable()) {
            $body->rewind();
        }

        try {
            while (! $body->eof()) {
                $chunk = $body->read(65536);
                $total += strlen($chunk);

                if ($total > self::DOWNLOAD_MAX_BYTES) {
                    throw new \RuntimeException('La imagen supera el tamaño máximo permitido (12 MB).');
                }

                fwrite($out, $chunk);
            }
        } catch (\Throwable $e) {
            fclose($out);
            @unlink($tmp);

            throw $e;
        }

        fclose($out);

        return $tmp;
    }

    /**
     * Procesa una imagen de banner (sin thumbnail).
     *
     * @return string path en el disco 'public'
     */
    public function banner(UploadedFile $file): string
    {
        $path = 'banners/'.Str::uuid().'.webp';

        $img = $this->manager->decodePath($file->getRealPath());
        $img->scaleDown(self::BANNER_MAX_W, self::BANNER_MAX_H);
        Storage::disk('public')->put($path, (string) $img->encode(new WebpEncoder(self::BANNER_Q)));

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

        $img = $this->manager->decodePath($file->getRealPath());
        $img->scaleDown(self::SETTINGS_MAX_W, self::SETTINGS_MAX_H);
        Storage::disk('public')->put($path, (string) $img->encode(new WebpEncoder(self::SETTINGS_Q)));

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

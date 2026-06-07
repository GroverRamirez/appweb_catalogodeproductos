<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Valida el tipo MIME real de un archivo de imagen leyendo sus magic bytes
 * con finfo_file(), ignorando la extensión y el tipo declarado por el cliente.
 *
 * Esto previene ataques con archivos polimorfos (ej: un script PHP renombrado
 * a .jpg) que pasarían la regla 'image' estándar de Laravel.
 */
class ValidImageMime implements ValidationRule
{
    /** MIME types permitidos */
    private const ALLOWED = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
    ];

    /** Extensiones seguras mapeadas a MIME (doble verificación) */
    private const EXTENSION_MAP = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/gif'  => ['gif'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('El campo :attribute debe ser un archivo subido.');
            return;
        }

        if (! $value->isValid()) {
            $fail('El archivo :attribute no se subió correctamente.');
            return;
        }

        $realPath = $value->getRealPath();

        if (! $realPath || ! is_readable($realPath)) {
            $fail('No se puede leer el archivo :attribute.');
            return;
        }

        $detectedMime = $this->detectMime($realPath);

        // 1) Verificar que el MIME detectado esté en la lista de permitidos
        if (! in_array($detectedMime, self::ALLOWED, true)) {
            $fail("El archivo :attribute no es una imagen válida (JPEG, PNG, GIF, WebP, AVIF). Tipo detectado: {$detectedMime}.");
            return;
        }

        // 2) Verificar que la extensión corresponda al MIME detectado
        //    (previene confusion entre tipos, ej: GIF renombrado a .jpg)
        $ext = strtolower($value->getClientOriginalExtension());
        $allowedExtensions = self::EXTENSION_MAP[$detectedMime] ?? [];

        if (! empty($ext) && ! empty($allowedExtensions) && ! in_array($ext, $allowedExtensions, true)) {
            $fail("La extensión '.{$ext}' no coincide con el tipo de imagen detectado ({$detectedMime}).");
        }
    }

    private function detectMime(string $path): string
    {
        // Preferir finfo (más preciso, lee magic bytes)
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);

            if ($mime !== false) {
                return $mime;
            }
        }

        // Fallback: mime_content_type (disponible en la mayoría de entornos PHP)
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
            if ($mime !== false) {
                return $mime;
            }
        }

        // Último recurso: leer magic bytes manualmente para JPEG, PNG, GIF, WebP
        return $this->readMagicBytes($path);
    }

    private function readMagicBytes(string $path): string
    {
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return 'application/octet-stream';
        }

        $bytes = fread($handle, 12);
        fclose($handle);

        if ($bytes === false || strlen($bytes) < 4) {
            return 'application/octet-stream';
        }

        // JPEG: FF D8 FF
        if (substr($bytes, 0, 3) === "\xFF\xD8\xFF") {
            return 'image/jpeg';
        }

        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (substr($bytes, 0, 8) === "\x89PNG\r\n\x1A\n") {
            return 'image/png';
        }

        // GIF: 47 49 46 38
        if (substr($bytes, 0, 4) === 'GIF8') {
            return 'image/gif';
        }

        // WebP: RIFF????WEBP
        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return 'application/octet-stream';
    }
}

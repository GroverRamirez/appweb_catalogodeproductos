<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generar nonce una sola vez por request y compartirlo con las vistas
        $nonce = base64_encode(Str::random(24));
        $request->attributes->set('csp-nonce', $nonce);
        view()->share('cspNonce', $nonce);

        $response = $next($request);

        // Solo aplicar CSP en respuestas HTML (no en JSON de Inertia / API)
        $contentType = $response->headers->get('Content-Type', '');
        $isHtml = str_contains($contentType, 'text/html') || $contentType === '';

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        );

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Evitar que los motores de búsqueda indexen el panel de administración.
        // Complementa el robots.txt: el header llega aunque el bot ignore el .txt.
        if ($request->is('admin', 'admin/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // CSP solo en producción: Vite dev server inyecta scripts/styles inline propios
        // (HMR client, hot-reload) que no pueden recibir nonce → rompería la app en local.
        if ($isHtml && ! app()->environment('local')) {
            $response->headers->set('Content-Security-Policy', $this->buildCsp($nonce));
        }

        return $response;
    }

    private function buildCsp(string $nonce): string
    {
        // Fuentes: la app usa Plus Jakarta Sans (Google) e Instrument Sans (Bunny)
        $styleFontOrigins = 'https://fonts.bunny.net https://fonts.googleapis.com';
        $fontFileOrigins  = 'https://fonts.bunny.net https://fonts.gstatic.com';

        $directives = [
            // Fuente por defecto: solo mismo origen
            "default-src 'self'",

            // Scripts: solo mismo origen + nonce para el inline dark-mode.
            // Vite en producción genera archivos externos en /build/ → 'self' es suficiente.
            "script-src 'self' 'nonce-{$nonce}'",

            // Estilos: mismo origen + nonce para inline + CDNs de fuentes.
            // 'unsafe-inline' es necesario para Vue 3 (:style bindings, transiciones)
            // y para librerías UI (popovers, dropdowns con posicionamiento inline).
            "style-src 'self' 'unsafe-inline' {$styleFontOrigins}",

            // Imágenes: mismo origen, data URIs, blobs y cualquier HTTPS
            // (necesario para imágenes externas en productos/banners/logos)
            "img-src 'self' data: blob: https:",

            // Archivos de fuentes tipográficas
            "font-src 'self' {$fontFileOrigins}",

            // XHR / fetch: solo mismo origen
            "connect-src 'self'",

            // Iframes: solo mismo origen (refuerza X-Frame-Options)
            "frame-ancestors 'self'",

            // Bloquea <base> tags maliciosos
            "base-uri 'self'",

            // Formularios solo al mismo origen
            "form-action 'self'",

            // Bloquear Flash, Java applets, etc.
            "object-src 'none'",

            // Web Workers: solo mismo origen o blobs generados en 'self'
            "worker-src 'self' blob:",
        ];

        return implode('; ', $directives);
    }
}

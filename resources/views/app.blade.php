<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- PWA Meta Tags --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#2563eb" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Mi Catálogo') }}">

        {{-- Inline script to detect system / stored dark mode preference and apply it immediately --}}
        <script nonce="{{ $cspNonce ?? '' }}">
            (function() {
                try {
                    const stored = localStorage.getItem('appearance');
                    const appearance = stored || '{{ $appearance ?? "system" }}';

                    if (appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    } else if (appearance === 'light') {
                        document.documentElement.classList.remove('dark');
                    }
                } catch (e) {}
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style nonce="{{ $cspNonce ?? '' }}">
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="sitemap" type="application/xml" title="Sitemap" href="/sitemap.xml">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>

        {{-- Registro del Service Worker para PWA --}}
        <script nonce="{{ $cspNonce ?? '' }}">
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('/sw.js').catch(function(err) {
                        console.warn('Error registrando Service Worker:', err);
                    });
                });
            }
        </script>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

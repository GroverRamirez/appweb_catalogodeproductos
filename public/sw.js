/**
 * Service Worker de PC Store Pro (PWA)
 * Soporta instalación en móviles (Android/iOS) y navegación con soporte offline.
 */
const CACHE_NAME = 'pcstore-pwa-v2';

// Sin '/': las páginas llevan datos de la sesión (nombre, correo) y no se
// guardan en el dispositivo.
const STATIC_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/favicon.svg',
    '/pwa-192x192.png',
    '/pwa-512x512.png',
];

// Instalación: precachear activos estáticos críticos
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.warn('Error precacheando recursos estáticos:', err);
            });
        }),
    );
    self.skipWaiting();
});

// Activación: limpieza de cachés antiguas y tomar control de clientes
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                }),
            );
        }),
    );
    self.clients.claim();
});

// Intercepción de peticiones (Fetch)
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Solo manejar peticiones GET del mismo origen
    if (request.method !== 'GET' || url.origin !== location.origin) {
        return;
    }

    // No cachear panel de administración, autenticación ni rutas transaccionales
    if (
        url.pathname.startsWith('/admin') ||
        url.pathname.startsWith('/login') ||
        url.pathname.startsWith('/logout') ||
        url.pathname.startsWith('/register') ||
        url.pathname.startsWith('/password') ||
        url.pathname.startsWith('/sanctum')
    ) {
        return;
    }

    // 1. Páginas: siempre de la red. Sin conexión se muestra offline.html.
    //    No se guardan copias: pueden traer datos personales (pedidos, perfil)
    //    y quedarían en el teléfono aunque el cliente cierre sesión.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html')),
        );
        return;
    }

    // Los comprobantes de pago no se guardan en la caché del dispositivo.
    if (url.pathname.startsWith('/storage/comprobantes_pago/')) {
        return;
    }

    // 2. Activos estáticos (imágenes, CSS, JS, fuentes): Cache first con actualización de fondo
    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/storage/') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.woff2')
    ) {
        event.respondWith(
            caches.match(request).then((cachedResponse) => {
                const fetchPromise = fetch(request)
                    .then((networkResponse) => {
                        if (networkResponse && networkResponse.status === 200) {
                            const responseClone = networkResponse.clone();
                            caches.open(CACHE_NAME).then((cache) => {
                                cache.put(request, responseClone);
                            });
                        }
                        return networkResponse;
                    })
                    .catch(() => cachedResponse);

                return cachedResponse || fetchPromise;
            }),
        );
    }
});

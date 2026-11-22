import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { networkInterfaces } from 'node:os';
import { defineConfig, loadEnv } from 'vite';

// IP de la PC en la red local (WiFi/LAN), para que otros equipos y celulares
// carguen los assets del servidor de desarrollo.
function lanAddress(): string | undefined {
    return Object.values(networkInterfaces())
        .flat()
        .find(
            (net) =>
                net &&
                net.family === 'IPv4' &&
                !net.internal &&
                /^(192\.168|10|172\.(1[6-9]|2\d|3[01]))\./.test(net.address),
        )?.address;
}

export default defineConfig(({ mode }) => {
    // Con SERVER_HOST=0.0.0.0 en el .env, `artisan serve` y Vite escuchan en
    // toda la red local: la app se abre en http://IP-de-la-PC:8020.
    const lan = loadEnv(mode, process.cwd(), '').SERVER_HOST === '0.0.0.0';
    const lanHost = lan ? lanAddress() : undefined;

    return {
        server: lanHost
            ? {
                  host: '0.0.0.0',
                  hmr: { host: lanHost },
                  cors: {
                      origin: /^https?:\/\/(localhost|127\.0\.0\.1|\[::1\]|[\w-]+\.test|192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2\d|3[01])\.\d+\.\d+)(:\d+)?$/,
                  },
              }
            : undefined,
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.ts'],
                refresh: true,
                fonts: [
                    bunny('Inter', {
                        weights: [400, 500, 600, 700],
                    }),
                    bunny('Plus Jakarta Sans', {
                        weights: [600, 700, 800],
                    }),
                ],
            }),
            inertia(),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            // En Docker las rutas se generan en la etapa de PHP: la de Node no
            // tiene PHP para correr `artisan wayfinder:generate`.
            ...(process.env.SKIP_WAYFINDER
                ? []
                : [
                      wayfinder({
                          formVariants: true,
                      }),
                  ]),
        ],
    };
});

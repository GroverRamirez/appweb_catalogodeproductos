import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name === 'Error':
                // Error.vue es standalone — define layout: null en defineOptions()
                return null;
            case name.startsWith('catalog/'):
                // Las páginas del catálogo público definen su propio PublicLayout
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    // Un solo contenedor de notificaciones para toda la app, fuera de los
    // layouts: así un aviso que llega justo al cambiar de layout (p. ej. la
    // bienvenida al pasar del login al panel) no se pierde.
    setup({ el, App, props, plugin }) {
        createApp({ render: () => [h(App, props), h(Toaster)] })
            .use(plugin)
            .mount(el!);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

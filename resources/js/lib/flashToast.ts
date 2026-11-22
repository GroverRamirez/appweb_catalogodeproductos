import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

// Los errores y advertencias se quedan más tiempo: suelen pedir que el
// usuario haga algo, y hay que darle tiempo a leerlos.
const DURATION: Record<FlashToast['type'], number> = {
    success: 4000,
    info: 5000,
    warning: 8000,
    error: 8000,
};

export function notify(data: FlashToast): void {
    toast[data.type](data.message, {
        description: data.description,
        duration: DURATION[data.type],
    });
}

let currentComponent = '';

/**
 * Notificaciones globales de la app.
 *
 * - Avisos del servidor: los controladores usan ->with('success'|'error'|...)
 *   o App\Support\Toast, y llegan por el canal flash de Inertia. Ese canal no
 *   se guarda en el historial, así que volver atrás no repite el aviso.
 * - Errores de validación: cada campo ya muestra su mensaje; la notificación
 *   deja claro que no se guardó nada.
 * - Sin conexión: el envío no llegó al servidor.
 */
export function initializeFlashToast(): void {
    router.on('navigate', (event) => {
        currentComponent = event.detail.page.component;
    });

    router.on('flash', (event) => {
        const flash = event.detail.flash as {
            toast?: FlashToast;
            toasts?: FlashToast[];
        };

        [
            ...(flash.toasts ?? []),
            ...(flash.toast ? [flash.toast] : []),
        ].forEach(notify);
    });

    router.on('error', (event) => {
        // En el login y el registro el mensaje bajo el campo es suficiente.
        if (currentComponent.startsWith('auth/')) {
            return;
        }

        const messages = Object.values(event.detail.errors).filter(Boolean);

        if (messages.length === 0) {
            return;
        }

        notify({
            type: 'error',
            message: 'No se guardaron los cambios',
            description:
                messages.length === 1
                    ? String(messages[0])
                    : `Revisa los ${messages.length} campos marcados en el formulario.`,
        });
    });

    router.on('networkError', () => {
        notify({
            type: 'error',
            message: 'No hay conexión con el servidor',
            description: 'Revisa tu conexión a internet y vuelve a intentarlo.',
        });
    });
}

import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Acceso a los roles y permisos del usuario autenticado, compartidos por
 * HandleInertiaRequests en `auth.roles` / `auth.permissions`.
 */
export function usePermissions() {
    const page = usePage();

    const roles = computed(() => page.props.auth?.roles ?? []);
    const permissions = computed(() => page.props.auth?.permissions ?? []);

    const can = (permission: string): boolean =>
        permissions.value.includes(permission);

    const canAny = (...wanted: string[]): boolean =>
        wanted.some((permission) => permissions.value.includes(permission));

    const hasRole = (role: string): boolean => roles.value.includes(role);

    return { roles, permissions, can, canAny, hasRole };
}

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { FolderOpen, Package, Tag } from 'lucide-vue-next';
import { computed } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

/**
 * Pestañas de "Gestión de Productos". Cada pestaña es una página propia
 * (navegación Inertia, no estado local) para que cada recurso conserve su
 * paginación, su búsqueda y su permiso; cambiar de pestaña no recarga la app.
 */
const { can } = usePermissions();
const page = usePage();

const tabs = computed(() =>
    [
        {
            label: 'Categorías',
            href: '/admin/categories',
            icon: FolderOpen,
            permission: 'categories.view',
        },
        {
            label: 'Marcas',
            href: '/admin/brands',
            icon: Tag,
            permission: 'brands.view',
        },
        {
            label: 'Productos',
            href: '/admin/products',
            icon: Package,
            permission: 'products.view',
        },
    ].filter((tab) => can(tab.permission)),
);

// La URL trae querystring (?q=...) y las pantallas de alta/edición cuelgan de
// la misma raíz, así que se compara por prefijo del path, no por igualdad.
const isActive = (href: string) => {
    const path = page.url.split('?')[0];

    return path === href || path.startsWith(`${href}/`);
};
</script>

<template>
    <!-- Sin borde inferior a proposito: va pegado debajo de PageHeader, que ya
         trae el suyo, y dos seguidos se ven como una linea doble. -->
    <div v-if="tabs.length > 1" class="flex flex-wrap gap-2">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors"
            :class="
                isActive(tab.href)
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
            "
            :aria-current="isActive(tab.href) ? 'page' : undefined"
        >
            <component :is="tab.icon" class="size-4" />
            {{ tab.label }}
        </Link>
    </div>
</template>

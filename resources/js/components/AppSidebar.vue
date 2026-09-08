<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Home,
    Image,
    LayoutGrid,
    MessageSquare,
    Package,
    PackagePlus,
    Settings as SettingsIcon,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    UserRound,
    Ticket,
    Truck,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { home } from '@/routes';
import { index as catalogIndex } from '@/routes/catalog';
import type { NavItem } from '@/types';

const { can } = usePermissions();

const page = usePage<{ pendingInquiries?: number | null }>();

type GatedNavItem = NavItem & { permission?: string };

/** Pestañas de Gestión de Productos, en el orden en que se muestran. */
const PRODUCT_TABS = [
    { permission: 'products.view', href: '/admin/products' },
    { permission: 'categories.view', href: '/admin/categories' },
    { permission: 'brands.view', href: '/admin/brands' },
];

const productsHref = computed(
    () => PRODUCT_TABS.find((tab) => can(tab.permission))?.href ?? null,
);

const mainNavItems = computed<NavItem[]>(() => {
    const items: GatedNavItem[] = [
        { title: 'Panel', href: '/admin', icon: LayoutGrid },
        // Categorías y Marcas ya no son entradas propias: son pestañas dentro
        // de Productos (ver ProductTabs.vue). El destino es la primera pestaña
        // que el usuario puede ver, para que un rol con permiso solo sobre
        // categorías no aterrice en un 403.
        {
            title: 'Productos',
            href: productsHref.value ?? '/admin/products',
            icon: Package,
        },
        {
            title: 'Clientes',
            href: '/admin/clients',
            icon: UserRound,
            permission: 'clients.view',
        },
        {
            title: 'Ventas',
            href: '/admin/sales',
            icon: ShoppingCart,
            permission: 'sales.view',
        },
        {
            title: 'Proveedores',
            href: '/admin/suppliers',
            icon: Truck,
            permission: 'inventory.view',
        },
        {
            title: 'Compras',
            href: '/admin/purchases',
            icon: PackagePlus,
            permission: 'inventory.view',
        },
        {
            title: 'Banners',
            href: '/admin/banners',
            icon: Image,
            permission: 'banners.view',
        },
        {
            title: 'Cupones',
            href: '/admin/coupons',
            icon: Ticket,
            permission: 'coupons.view',
        },
        {
            title: 'Consultas',
            href: '/admin/inquiries',
            icon: MessageSquare,
            permission: 'inquiries.view',
            badge: page.props.pendingInquiries || null,
        },
        {
            title: 'Reportes',
            href: '/admin/reportes',
            icon: BarChart3,
            permission: 'reports.view',
        },
        {
            title: 'Usuarios',
            href: '/admin/users',
            icon: Users,
            permission: 'users.view',
        },
        {
            title: 'Roles',
            href: '/admin/roles',
            icon: ShieldCheck,
            permission: 'roles.view',
        },
        {
            title: 'Configuración',
            href: '/admin/settings',
            icon: SettingsIcon,
            permission: 'settings.view',
        },
    ];

    return items.filter((item) => {
        // Productos se oculta solo si no puede ver ninguna de sus pestañas.
        if (item.title === 'Productos') {
            return productsHref.value !== null;
        }

        return !item.permission || can(item.permission);
    });
});

const publicCatalogNavItems: NavItem[] = [
    { title: 'Página principal', href: home.url(), icon: Home },
    { title: 'Ver catálogo', href: catalogIndex.url(), icon: ShoppingBag },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="pb-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="rounded-xl hover:bg-sidebar-accent"
                    >
                        <Link href="/admin">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-3">
            <NavMain :items="mainNavItems" />
            <NavMain :items="publicCatalogNavItems" label="Catálogo público" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/80 pt-3">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    Home,
    Image,
    LayoutGrid,
    MessageSquare,
    Package,
    Settings as SettingsIcon,
    ShieldCheck,
    ShoppingBag,
    Tag,
    Tags,
    Ticket,
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

type GatedNavItem = NavItem & { permission?: string };

const mainNavItems = computed<NavItem[]>(() => {
    const items: GatedNavItem[] = [
        { title: 'Panel', href: '/admin', icon: LayoutGrid },
        {
            title: 'Productos',
            href: '/admin/products',
            icon: Package,
            permission: 'products.view',
        },
        {
            title: 'Categorías',
            href: '/admin/categories',
            icon: Tags,
            permission: 'categories.view',
        },
        {
            title: 'Marcas',
            href: '/admin/brands',
            icon: Tag,
            permission: 'brands.view',
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

    return items.filter((item) => !item.permission || can(item.permission));
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

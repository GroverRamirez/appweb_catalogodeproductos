<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Home,
    Image,
    LayoutGrid,
    MessageSquare,
    Package,
    Settings as SettingsIcon,
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
import { home } from '@/routes';
import { index as catalogIndex } from '@/routes/catalog';
import type { NavItem } from '@/types';

const page = usePage();

const roles = computed(() => (page.props.auth?.roles ?? []) as string[]);
const isAdmin = computed(() => roles.value.includes('admin'));

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Panel', href: '/admin', icon: LayoutGrid },
        { title: 'Productos', href: '/admin/products', icon: Package },
        { title: 'Categorías', href: '/admin/categories', icon: Tags },
        { title: 'Marcas', href: '/admin/brands', icon: Tag },
        { title: 'Consultas', href: '/admin/inquiries', icon: MessageSquare },
        { title: 'Reportes', href: '/admin/reportes', icon: BarChart3 },
    ];

    if (isAdmin.value) {
        items.push(
            { title: 'Banners', href: '/admin/banners', icon: Image },
            { title: 'Cupones', href: '/admin/coupons', icon: Ticket },
            { title: 'Usuarios', href: '/admin/users', icon: Users },
            {
                title: 'Configuración',
                href: '/admin/settings',
                icon: SettingsIcon,
            },
        );
    }

    return items;
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

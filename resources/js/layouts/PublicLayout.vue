<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    LogIn,
    LogOut,
    Mail,
    MapPin,
    Menu,
    Phone,
    Search,
    ShoppingCart,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CartDrawer from '@/components/catalog/CartDrawer.vue';
import WhatsAppFab from '@/components/catalog/WhatsAppFab.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { useCart } from '@/composables/useCart';
import { useReveal } from '@/composables/useReveal';
import { useTranslations } from '@/composables/useTranslations';
import type { StoreSettings } from '@/lib/catalog';
import { login, logout } from '@/routes';

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const user = computed(() => page.props.auth?.user);
const currentPath = computed(() => page.url.split('?')[0]);
const permissions = computed(
    () => (page.props.auth?.permissions ?? []) as string[],
);
const isStaff = computed(() => permissions.value.length > 0);
const whatsappHref = computed(() => {
    const whatsapp = store.value.whatsapp?.replace(/[^0-9]/g, '');

    return whatsapp ? `https://wa.me/${whatsapp}` : null;
});
const secondaryNavItems = computed(() => [
    {
        label: 'Categorías',
        href: '/catalogo',
        active: false,
        withIcon: true,
    },
    {
        label: 'Marcas',
        href: '/catalogo',
        active: false,
        withIcon: false,
    },
    {
        label: 'Novedades',
        href: '/catalogo?sort=newest',
        active: page.url.includes('sort=newest'),
        withIcon: false,
    },
    {
        label: 'Ofertas',
        href: '/catalogo?sort=discount',
        active: page.url.includes('sort=discount'),
        withIcon: false,
    },
    {
        label: 'Catálogo',
        href: '/catalogo',
        active: currentPath.value.startsWith('/catalogo'),
        withIcon: false,
    },
]);
const flash = computed(
    () => (page.props.flash ?? {}) as { success?: string; error?: string },
);

const q = ref(
    new URLSearchParams(
        typeof window !== 'undefined' ? window.location.search : '',
    ).get('q') ?? '',
);

const doSearch = () => {
    router.get('/catalogo', { q: q.value }, { preserveState: false });
};

const handleLogout = () => {
    router.post(logout().url, {}, { onSuccess: () => router.flushAll() });
};

const { count: cartCount, drawerOpen } = useCart();
const openCart = () => (drawerOpen.value = true);

const { t } = useTranslations();

const showSecondaryNav = ref(true);
const updateSecondaryNav = () => {
    if (typeof window === 'undefined') {
        return;
    }

    showSecondaryNav.value = window.scrollY < 80;
};

onMounted(() => {
    updateSecondaryNav();
    window.addEventListener('scroll', updateSecondaryNav, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', updateSecondaryNav);
});

const showFlash = ref(false);
watch(
    () => flash.value.success,
    (v) => {
        if (v) {
            showFlash.value = true;
            setTimeout(() => (showFlash.value = false), 4000);
        }
    },
    { immediate: true },
);

// scroll-reveal en todas las páginas envueltas en PublicLayout
useReveal();
</script>

<template>
    <Head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin=""
        />
        <link
            rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        />
        <link v-if="store.favicon_url" rel="icon" :href="store.favicon_url" />
    </Head>

    <div
        class="storefront flex min-h-screen flex-col overflow-x-clip bg-background text-foreground"
    >
        <!-- Topbar de contacto -->
        <div
            v-if="store.whatsapp || store.email"
            class="hidden bg-[hsl(222_33%_13%)] text-[12px] text-white/80 md:block"
        >
            <div
                class="mx-auto flex max-w-[1400px] items-center justify-between gap-4 px-4 py-1.5"
            >
                <div class="flex items-center gap-4">
                    <span
                        v-if="store.address"
                        class="inline-flex items-center gap-1 opacity-90"
                    >
                        <MapPin class="size-3" /> {{ store.address }}
                    </span>
                    <span
                        v-if="store.hours"
                        class="inline-flex items-center gap-1 opacity-70"
                    >
                        {{ store.hours }}
                    </span>
                </div>
                <div class="flex items-center gap-4">
                    <a
                        v-if="store.whatsapp"
                        :href="`https://wa.me/${store.whatsapp.replace(/[^0-9]/g, '')}`"
                        target="_blank"
                        class="inline-flex items-center gap-1 transition hover:text-white"
                    >
                        <Phone class="size-3" /> {{ store.whatsapp }}
                    </a>
                    <a
                        v-if="store.email"
                        :href="`mailto:${store.email}`"
                        class="inline-flex items-center gap-1 transition hover:text-white"
                    >
                        <Mail class="size-3" /> {{ store.email }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Header -->
        <header class="sticky top-0 z-30 border-b border-border bg-background">
            <div
                class="mx-auto flex h-16 max-w-[1400px] items-center gap-3 px-4 md:gap-6"
            >
                <div class="flex min-w-0 flex-1 items-center gap-2 md:gap-4">
                <Link href="/" class="flex shrink-0 items-center gap-2.5">
                    <img
                        v-if="store.logo_url"
                        :src="store.logo_url"
                        :alt="store.name"
                        class="h-9 w-auto max-w-[160px] object-contain"
                    />
                    <template v-else>
                        <span
                            class="grid size-9 place-items-center rounded-md bg-primary text-sm font-bold text-primary-foreground"
                        >
                            {{ (store.name || 'M').charAt(0).toUpperCase() }}
                        </span>
                        <span
                            class="font-display text-lg font-bold tracking-tight"
                        >
                            {{ store.name }}
                        </span>
                    </template>
                </Link>

                <nav class="ml-2 hidden gap-1 md:flex">
                    <Link
                        href="/"
                        class="rounded-md px-3 py-2 text-sm font-medium transition"
                        :class="
                            currentPath === '/'
                                ? 'text-primary'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ t('home') }}
                    </Link>
                    <Link
                        href="/catalogo"
                        class="rounded-md px-3 py-2 text-sm font-medium transition"
                        :class="
                            currentPath.startsWith('/catalogo')
                                ? 'text-primary'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ t('catalog') }}
                    </Link>
                </nav>
                </div>

                <form
                    @submit.prevent="doSearch"
                    class="relative hidden max-w-xl flex-1 md:block"
                >
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="q"
                        :placeholder="t('search_placeholder')"
                        class="h-10 w-full rounded-md border-input bg-background pl-10 focus-visible:border-ring"
                    />
                </form>

                <div class="flex flex-1 items-center justify-end gap-1 md:gap-2">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="relative rounded-md hover:bg-muted"
                        @click="openCart"
                        aria-label="Carrito"
                    >
                        <ShoppingCart class="size-5" />
                        <span
                            v-if="cartCount > 0"
                            class="absolute -top-1 -right-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-none font-bold text-primary-foreground"
                        >
                            {{ cartCount }}
                        </span>
                    </Button>

                    <template v-if="user">
                        <Button
                            v-if="isStaff"
                            variant="outline"
                            size="sm"
                            as-child
                            class="rounded-md font-medium"
                        >
                            <Link href="/admin">
                                <LayoutDashboard class="size-4" />
                                <span class="hidden sm:inline">{{
                                    t('panel')
                                }}</span>
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="rounded-md hover:bg-muted"
                            @click="handleLogout"
                        >
                            <LogOut class="size-4" />
                            <span class="hidden sm:inline">{{
                                t('logout')
                            }}</span>
                        </Button>
                    </template>
                    <Button
                        v-else
                        variant="default"
                        size="sm"
                        as-child
                        class="hidden rounded-md sm:inline-flex"
                    >
                        <Link :href="login()">
                            <LogIn class="size-4" />
                            {{ t('login') }}
                        </Link>
                    </Button>

                    <Sheet>
                        <SheetTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="rounded-md md:hidden"
                            >
                                <Menu class="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-72 p-6">
                            <h3 class="mb-4 font-display text-lg font-bold">
                                {{ store.name }}
                            </h3>
                            <form
                                @submit.prevent="doSearch"
                                class="relative mb-4"
                            >
                                <Search
                                    class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    v-model="q"
                                    :placeholder="t('search_placeholder')"
                                    class="pl-8"
                                />
                            </form>
                            <nav class="flex flex-col gap-1">
                                <Link
                                    href="/"
                                    class="rounded-md px-3 py-2 text-sm font-medium hover:bg-muted"
                                >
                                    {{ t('home') }}
                                </Link>
                                <Link
                                    href="/catalogo"
                                    class="rounded-md px-3 py-2 text-sm font-medium hover:bg-muted"
                                >
                                    {{ t('catalog') }}
                                </Link>
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
            <Transition
                enter-active-class="transition-opacity duration-100 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-100 ease-in"
                leave-to-class="opacity-0"
            >
                <nav
                    v-show="showSecondaryNav"
                    class="hidden bg-brand text-brand-foreground shadow-sm shadow-primary/10 md:block"
                    aria-label="Navegación secundaria"
                >
                    <div
                        class="mx-auto flex h-12 max-w-[1400px] items-center gap-8 px-4 text-sm font-bold uppercase"
                    >
                        <Link
                            v-for="item in secondaryNavItems"
                            :key="item.label"
                            :href="item.href"
                            class="inline-flex h-full items-center gap-2 whitespace-nowrap border-b-2 border-transparent transition hover:border-brand-foreground hover:text-brand-foreground"
                            :class="{
                                'border-brand-foreground text-brand-foreground':
                                    item.active,
                                'text-brand-foreground/85': !item.active,
                            }"
                        >
                            <Menu v-if="item.withIcon" class="size-4" />
                            <span>{{ item.label }}</span>
                        </Link>
                        <a
                            v-if="whatsappHref"
                            :href="whatsappHref"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-full items-center whitespace-nowrap border-b-2 border-transparent text-brand-foreground/85 transition hover:border-brand-foreground hover:text-brand-foreground"
                        >
                            Contactos
                        </a>
                    </div>
                </nav>
            </Transition>
        </header>

        <transition
            enter-active-class="transition duration-300"
            enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-300"
            leave-to-class="-translate-y-2 opacity-0"
        >
            <div
                v-if="showFlash && flash.success"
                class="sticky top-20 z-20 mx-auto max-w-[1400px] px-4 pt-3"
            >
                <div
                    class="rounded-md border border-primary/30 bg-accent px-4 py-3 text-sm font-medium text-accent-foreground"
                >
                    {{ flash.success }}
                </div>
            </div>
        </transition>

        <main class="flex-1">
            <slot />
        </main>

        <CartDrawer />
        <WhatsAppFab />

        <footer class="mt-16 bg-[hsl(222_33%_13%)] text-white">
            <div
                class="mx-auto grid max-w-[1400px] gap-10 px-4 py-14 text-sm md:grid-cols-4"
            >
                <div class="md:col-span-2">
                    <h4 class="mb-2 font-display text-xl font-bold">
                        {{ store.name }}
                    </h4>
                    <p class="max-w-md text-white/60">
                        {{ store.tagline }}
                    </p>
                </div>
                <div>
                    <h4
                        class="mb-3 text-xs font-semibold tracking-wider text-white/50 uppercase"
                    >
                        {{ t('contact') }}
                    </h4>
                    <ul class="space-y-2 text-white/75">
                        <li
                            v-if="store.whatsapp"
                            class="flex items-center gap-2"
                        >
                            <Phone class="size-4 text-white/40" />
                            {{ store.whatsapp }}
                        </li>
                        <li v-if="store.email" class="flex items-center gap-2">
                            <Mail class="size-4 text-white/40" />
                            {{ store.email }}
                        </li>
                        <li v-if="store.address" class="flex items-start gap-2">
                            <MapPin class="mt-0.5 size-4 text-white/40" />
                            <span>{{ store.address }}</span>
                        </li>
                        <li
                            v-if="store.hours"
                            class="flex items-center gap-2 text-white/50"
                        >
                            {{ t('hours') }}: {{ store.hours }}
                        </li>
                    </ul>
                </div>
                <div>
                    <h4
                        class="mb-3 text-xs font-semibold tracking-wider text-white/50 uppercase"
                    >
                        {{ t('navigation') }}
                    </h4>
                    <ul class="space-y-2 text-white/75">
                        <li>
                            <Link href="/" class="transition hover:text-white">
                                {{ t('home') }}
                            </Link>
                        </li>
                        <li>
                            <Link
                                href="/catalogo"
                                class="transition hover:text-white"
                            >
                                {{ t('catalog') }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
            <div
                class="border-t border-white/10 py-3 text-center text-xs text-white/40"
            >
                © {{ new Date().getFullYear() }} {{ store.name }}.
                {{ t('rights_reserved') }}
            </div>
        </footer>
    </div>
</template>

<style>
/* La tienda pública es siempre clara, aunque el usuario tenga el panel en modo
   oscuro o el sistema en dark. Redefinimos los tokens claros en el contenedor;
   por herencia de variables CSS, todo el subárbol del storefront los usa. */
.storefront {
    --background: hsl(0 0% 100%);
    --foreground: hsl(222 33% 14%);
    --card: hsl(0 0% 100%);
    --card-foreground: hsl(222 33% 14%);
    --popover: hsl(0 0% 100%);
    --popover-foreground: hsl(222 33% 14%);
    --primary: hsl(218 79% 42%);
    --primary-foreground: hsl(0 0% 100%);
    --secondary: hsl(220 16% 95%);
    --secondary-foreground: hsl(222 30% 20%);
    --muted: hsl(220 16% 96%);
    --muted-foreground: hsl(220 9% 43%);
    --accent: hsl(218 60% 96%);
    --accent-foreground: hsl(218 79% 32%);
    --brand: hsl(218 79% 42%);
    --brand-foreground: hsl(0 0% 100%);
    --destructive: hsl(0 70% 45%);
    --destructive-foreground: hsl(0 0% 100%);
    --border: hsl(220 15% 90%);
    --input: hsl(220 15% 86%);
    --ring: hsl(218 79% 42%);
}
</style>

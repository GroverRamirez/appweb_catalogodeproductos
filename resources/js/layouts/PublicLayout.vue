<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Clock,
    Download,
    Flame,
    Heart,
    Home,
    LayoutDashboard,
    LayoutGrid,
    LogIn,
    LogOut,
    Mail,
    MapPin,
    Menu,
    MessageCircle,
    Moon,
    Package,
    Phone,
    ShoppingCart,
    Smartphone,
    Sparkles,
    Sun,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import CartDrawer from '@/components/catalog/CartDrawer.vue';
import LiveSearch from '@/components/catalog/LiveSearch.vue';
import WhatsAppFab from '@/components/catalog/WhatsAppFab.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useAppearance } from '@/composables/useAppearance';
import { useCart } from '@/composables/useCart';
import { useReveal } from '@/composables/useReveal';
import { useTranslations } from '@/composables/useTranslations';
import { useWishlist } from '@/composables/useWishlist';
import { formatRate } from '@/lib/catalog';
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
        label: 'Inicio',
        href: '/',
        active: currentPath.value === '/',
        icon: Home,
    },
    {
        label: 'Todo el catálogo',
        href: '/catalogo',
        active:
            currentPath.value.startsWith('/catalogo') &&
            !page.url.includes('sort='),
        icon: LayoutGrid,
    },
    {
        label: 'Novedades',
        href: '/catalogo?sort=newest',
        active: page.url.includes('sort=newest'),
        icon: Sparkles,
    },
    {
        label: 'Ofertas',
        href: '/catalogo?sort=discount',
        active: page.url.includes('sort=discount'),
        icon: Flame,
    },
    {
        label: 'Favoritos',
        href: '/favoritos',
        active: currentPath.value === '/favoritos',
        icon: Heart,
    },
    ...(user.value && !isStaff.value
        ? [
              {
                  label: 'Mis Pedidos',
                  href: '/mis-pedidos',
                  active: currentPath.value.startsWith('/mis-pedidos'),
                  icon: Package,
              },
          ]
        : []),
]);
const mobileMenuItems = computed(() =>
    secondaryNavItems.value.map(({ label, href }) => ({ label, href })),
);
// Destino/ícono/etiqueta del último tab de la barra móvil: staff va al panel,
// cliente logueado a su cuenta, invitado al login.
const accountHref = computed(() => {
    if (!user.value) {
        return login();
    }

    return isStaff.value ? '/admin' : '/mis-pedidos';
});
const accountIcon = computed(() => {
    if (!user.value) {
        return LogIn;
    }

    return isStaff.value ? LayoutDashboard : Package;
});
const accountActive = computed(() =>
    isStaff.value
        ? currentPath.value.startsWith('/admin')
        : currentPath.value.startsWith('/mis-pedidos'),
);

const handleLogout = () => {
    router.post(logout().url, {}, { onSuccess: () => router.flushAll() });
};

const { count: cartCount, drawerOpen } = useCart();
const { count: wishlistCount, sync: syncWishlist } = useWishlist();
const openCart = () => (drawerOpen.value = true);

// Modo Oscuro / Claro en tienda pública
const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    const next = resolvedAppearance.value === 'dark' ? 'light' : 'dark';
    updateAppearance(next);
};

// PWA: Detección y disparo de prompt de instalación
interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

const deferredPrompt = ref<BeforeInstallPromptEvent | null>(null);
const canInstallPwa = ref(false);
const isPwaInstalled = ref(false);

const onBeforeInstallPrompt = (e: Event) => {
    e.preventDefault();
    deferredPrompt.value = e as BeforeInstallPromptEvent;
    canInstallPwa.value = true;
};

const onAppInstalled = () => {
    canInstallPwa.value = false;
    isPwaInstalled.value = true;
    deferredPrompt.value = null;
};

const promptInstallPwa = async () => {
    if (!deferredPrompt.value) {
        return;
    }

    deferredPrompt.value.prompt();
    const choice = await deferredPrompt.value.userChoice;

    if (choice?.outcome === 'accepted') {
        canInstallPwa.value = false;
        isPwaInstalled.value = true;
    }

    deferredPrompt.value = null;
};

// El contador del carrito sale de localStorage (solo cliente): renderizar el
// badge recién al montar evita el mismatch de hidratación con el SSR.
const mounted = ref(false);

const { t } = useTranslations();

const showSecondaryNav = ref(true);

// Histéresis para evitar parpadeo: la barra secundaria (48px) vive dentro del
// header sticky, así que ocultarla acorta el header y desplaza el scroll. Con un
// solo umbral eso recrea la condición y entra en bucle. Usamos dos umbrales con
// una zona muerta (60–140) mayor que la altura de la barra, de modo que el salto
// de layout al ocultarla nunca vuelve a cruzar el umbral de mostrarla.
const updateSecondaryNav = () => {
    if (typeof window === 'undefined') {
        return;
    }

    const y = window.scrollY;

    if (y > 140 && showSecondaryNav.value) {
        showSecondaryNav.value = false;
    } else if (y < 60 && !showSecondaryNav.value) {
        showSecondaryNav.value = true;
    }
};

// Throttle con requestAnimationFrame: una sola medición por frame, no por evento.
let navTicking = false;
const onScroll = () => {
    if (navTicking) {
        return;
    }

    navTicking = true;
    window.requestAnimationFrame(() => {
        updateSecondaryNav();
        navTicking = false;
    });
};

onMounted(() => {
    mounted.value = true;

    if (user.value) {
        syncWishlist();
    }

    updateSecondaryNav();
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.addEventListener('appinstalled', onAppInstalled);

    if (
        typeof window !== 'undefined' &&
        window.matchMedia('(display-mode: standalone)').matches
    ) {
        isPwaInstalled.value = true;
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.removeEventListener('appinstalled', onAppInstalled);
});

// scroll-reveal en todas las páginas envueltas en PublicLayout
useReveal();
</script>

<template>
    <Head>
        <link v-if="store.favicon_url" rel="icon" :href="store.favicon_url" />
    </Head>

    <div
        class="storefront flex min-h-screen flex-col overflow-x-clip bg-background pb-16 text-foreground md:pb-0"
    >
        <!-- Topbar de contacto -->
        <div
            v-if="store.whatsapp || store.email || store.exchange_rate"
            class="hidden border-b border-border/70 bg-muted/60 text-[12px] text-muted-foreground md:block"
        >
            <div
                class="mx-auto flex h-9 max-w-[1400px] items-center justify-between gap-4 px-6 lg:px-8"
            >
                <div class="flex items-center gap-5">
                    <span
                        v-if="store.address"
                        class="inline-flex items-center gap-1.5"
                    >
                        <MapPin class="size-3.5" /> {{ store.address }}
                    </span>
                    <span
                        v-if="store.hours"
                        class="inline-flex items-center gap-1.5"
                    >
                        <Clock class="size-3.5" /> {{ store.hours }}
                    </span>
                </div>
                <div class="flex items-center gap-5">
                    <!-- Los precios se publican en bolivianos al tipo de cambio
                         del día: decir cuál es evita la pregunta de por qué el
                         mismo producto costaba otra cosa la semana pasada. -->
                    <span
                        v-if="store.exchange_rate"
                        class="inline-flex items-center gap-1.5 rounded-full bg-card px-2.5 py-0.5 ring-1 ring-border"
                        :title="
                            store.exchange_rate_source === 'bcb'
                                ? `Cotización oficial del BCB${store.exchange_rate_updated_at ? ` · actualizada el ${store.exchange_rate_updated_at}` : ''}`
                                : 'Cotización cargada por la tienda'
                        "
                    >
                        <span
                            class="size-1.5 rounded-full bg-emerald-500"
                        ></span>
                        Tipo de cambio:
                        <strong class="tabular font-semibold text-foreground">
                            {{ formatRate(store.exchange_rate) }} Bs
                        </strong>
                    </span>
                    <a
                        v-if="store.whatsapp"
                        :href="`https://wa.me/${store.whatsapp.replace(/[^0-9]/g, '')}`"
                        target="_blank"
                        class="inline-flex items-center gap-1.5 transition hover:text-foreground"
                    >
                        <Phone class="size-3.5" /> {{ store.whatsapp }}
                    </a>
                    <a
                        v-if="store.email"
                        :href="`mailto:${store.email}`"
                        class="inline-flex items-center gap-1.5 transition hover:text-foreground"
                    >
                        <Mail class="size-3.5" /> {{ store.email }}
                    </a>
                    <button
                        v-if="canInstallPwa"
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary transition hover:bg-primary/20"
                        @click="promptInstallPwa"
                    >
                        <Smartphone class="size-3" /> Instalar App
                    </button>
                </div>
            </div>
        </div>

        <!-- Header -->
        <header
            class="sticky top-0 z-30 border-b border-border/80 bg-card"
            :class="showSecondaryNav ? '' : 'elev-1'"
        >
            <div
                class="mx-auto flex h-[68px] max-w-[1400px] items-center gap-3 px-4 sm:px-6 md:gap-8 lg:px-8"
            >
                <!-- Tres columnas como en Digicorp: logo a la izquierda, buscador
                     al centro y acciones a la derecha, ocupando todo el ancho
                     del contenedor (las columnas laterales crecen por igual). -->
                <div class="flex min-w-0 flex-1 basis-0 items-center">
                    <Link
                        href="/"
                        class="flex min-w-0 shrink-0 items-center gap-2.5"
                    >
                        <img
                            v-if="store.logo_url"
                            :src="store.logo_url"
                            :alt="store.name"
                            class="h-9 w-auto max-w-[160px] object-contain"
                        />
                        <template v-else>
                            <span
                                class="grid size-9 place-items-center rounded-xl bg-primary font-display text-base font-extrabold text-primary-foreground"
                            >
                                {{
                                    (store.name || 'M').charAt(0).toUpperCase()
                                }}
                            </span>
                            <span
                                class="truncate font-display text-lg font-extrabold tracking-tight"
                            >
                                {{ store.name }}
                            </span>
                        </template>
                    </Link>
                </div>

                <LiveSearch
                    class="hidden max-w-2xl flex-[2] md:block"
                    :placeholder="t('search_placeholder')"
                />

                <div
                    class="flex flex-1 basis-0 items-center justify-end gap-1 md:gap-1.5"
                >
                    <Button
                        variant="ghost"
                        size="icon"
                        as-child
                        class="relative rounded-full hover:bg-muted"
                        aria-label="Favoritos"
                    >
                        <Link href="/favoritos">
                            <Heart class="size-5" />
                            <span
                                v-if="mounted && wishlistCount > 0"
                                class="absolute -top-0.5 -right-0.5 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-none font-bold text-primary-foreground ring-2 ring-card"
                            >
                                {{ wishlistCount }}
                            </span>
                        </Link>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="relative rounded-full hover:bg-muted"
                        @click="openCart"
                        aria-label="Carrito"
                    >
                        <ShoppingCart class="size-5" />
                        <span
                            v-if="mounted && cartCount > 0"
                            class="absolute -top-0.5 -right-0.5 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-none font-bold text-primary-foreground ring-2 ring-card"
                        >
                            {{ cartCount }}
                        </span>
                    </Button>

                    <!-- Selector de Modo Oscuro / Claro -->
                    <Button
                        variant="ghost"
                        size="icon"
                        class="rounded-full hover:bg-muted"
                        :aria-label="
                            resolvedAppearance === 'dark'
                                ? 'Cambiar a modo claro'
                                : 'Cambiar a modo oscuro'
                        "
                        :title="
                            resolvedAppearance === 'dark'
                                ? 'Cambiar a modo claro'
                                : 'Cambiar a modo oscuro'
                        "
                        @click="toggleTheme"
                    >
                        <Sun
                            v-if="resolvedAppearance === 'dark'"
                            class="size-5 text-amber-400 transition-transform hover:rotate-45"
                        />
                        <Moon
                            v-else
                            class="size-5 text-muted-foreground transition-transform hover:-rotate-12"
                        />
                    </Button>

                    <!-- Botón para instalar PWA (si está disponible) -->
                    <Button
                        v-if="canInstallPwa"
                        variant="outline"
                        size="sm"
                        class="hidden h-8 gap-1.5 rounded-full border-primary/30 text-xs font-medium text-primary hover:bg-primary/10 lg:inline-flex"
                        @click="promptInstallPwa"
                    >
                        <Download class="size-3.5" />
                        Instalar App
                    </Button>

                    <span
                        class="mx-1.5 hidden h-6 w-px bg-border sm:block"
                    ></span>

                    <template v-if="user">
                        <span
                            class="hidden max-w-32 truncate text-sm font-medium text-muted-foreground lg:inline"
                        >
                            Hola, {{ user.name.split(' ')[0] }}
                        </span>
                        <Button
                            v-if="isStaff"
                            variant="outline"
                            size="sm"
                            as-child
                            class="rounded-full font-medium"
                        >
                            <Link href="/admin">
                                <LayoutDashboard class="size-4" />
                                <span class="hidden sm:inline">{{
                                    t('panel')
                                }}</span>
                            </Link>
                        </Button>
                        <Button
                            v-else
                            variant="outline"
                            size="sm"
                            as-child
                            class="rounded-full font-medium"
                        >
                            <Link href="/mis-pedidos">
                                <Package class="size-4" />
                                <span class="hidden sm:inline"
                                    >Mis Pedidos</span
                                >
                            </Link>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="rounded-full hover:bg-muted"
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
                        class="hidden h-9 rounded-full px-4 sm:inline-flex"
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
                                class="rounded-full md:hidden"
                                aria-label="Menú"
                            >
                                <Menu class="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-72 p-6">
                            <SheetTitle
                                class="mb-4 font-display text-lg font-bold"
                            >
                                {{ store.name }}
                            </SheetTitle>
                            <SheetDescription class="sr-only">
                                Menú de navegación y buscador de la tienda
                            </SheetDescription>
                            <LiveSearch
                                class="mb-4"
                                :placeholder="t('search_placeholder')"
                                compact
                            />
                            <nav class="flex flex-col gap-1">
                                <Link
                                    v-for="item in mobileMenuItems"
                                    :key="item.label"
                                    :href="item.href"
                                    class="rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-muted"
                                >
                                    {{ item.label }}
                                </Link>
                            </nav>

                            <!-- Instalación PWA en móvil -->
                            <div
                                v-if="canInstallPwa"
                                class="mt-4 rounded-xl border border-primary/30 bg-primary/5 p-3 text-xs"
                            >
                                <div
                                    class="mb-1 flex items-center gap-2 font-semibold text-foreground"
                                >
                                    <Smartphone class="size-4 text-primary" />
                                    <span>App Móvil</span>
                                </div>
                                <p class="mb-2.5 text-muted-foreground">
                                    Instala la tienda en tu pantalla de inicio
                                    para navegar más rápido.
                                </p>
                                <Button
                                    size="sm"
                                    class="h-8 w-full gap-1.5 rounded-lg text-xs font-medium"
                                    @click="promptInstallPwa"
                                >
                                    <Download class="size-3.5" />
                                    Instalar App
                                </Button>
                            </div>

                            <!-- Selector de Modo Oscuro / Claro en móvil -->
                            <div
                                class="mt-4 flex items-center justify-between border-t border-border pt-4"
                            >
                                <span
                                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Tema visual
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted"
                                    @click="toggleTheme"
                                >
                                    <Sun
                                        v-if="resolvedAppearance === 'dark'"
                                        class="size-3.5 text-amber-400"
                                    />
                                    <Moon
                                        v-else
                                        class="size-3.5 text-muted-foreground"
                                    />
                                    <span>{{
                                        resolvedAppearance === 'dark'
                                            ? 'Modo Oscuro'
                                            : 'Modo Claro'
                                    }}</span>
                                </button>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>

            <!-- Búsqueda visible en móvil, debajo del logo -->
            <LiveSearch
                class="mx-4 mb-3 sm:mx-6 md:hidden"
                :placeholder="t('search_placeholder')"
                compact
            />

            <Transition
                enter-active-class="transition-opacity duration-100 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-100 ease-in"
                leave-to-class="opacity-0"
            >
                <nav
                    v-show="showSecondaryNav"
                    class="hidden bg-primary text-primary-foreground md:block"
                    aria-label="Navegación secundaria"
                >
                    <div
                        class="mx-auto flex h-12 max-w-[1400px] items-stretch gap-1 px-6 text-[13px] font-bold tracking-wide uppercase lg:px-8"
                    >
                        <Link
                            v-for="item in secondaryNavItems"
                            :key="item.label"
                            :href="item.href"
                            class="inline-flex items-center gap-2 border-b-[3px] px-3.5 pt-[3px] whitespace-nowrap transition"
                            :class="
                                item.active
                                    ? 'border-white text-white'
                                    : 'border-transparent text-white/85 hover:bg-white/10 hover:text-white'
                            "
                        >
                            <component
                                :is="item.icon"
                                v-if="item.icon"
                                class="size-4"
                            />
                            <span>{{ item.label }}</span>
                        </Link>
                        <a
                            v-if="whatsappHref"
                            :href="whatsappHref"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ml-auto inline-flex items-center gap-2 border-b-[3px] border-transparent px-3.5 pt-[3px] whitespace-nowrap text-white/85 transition hover:bg-white/10 hover:text-white"
                        >
                            <MessageCircle class="size-4" />
                            Contactos
                        </a>
                    </div>
                </nav>
            </Transition>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <CartDrawer />
        <WhatsAppFab />

        <!-- Barra de navegación fija (solo móvil) -->
        <nav
            class="fixed inset-x-0 bottom-0 z-30 flex items-stretch justify-around border-t border-border bg-card pb-[env(safe-area-inset-bottom)] md:hidden"
            aria-label="Navegación móvil"
        >
            <Link
                href="/"
                class="mobile-tab"
                :class="{ 'is-active': currentPath === '/' }"
            >
                <Home class="size-5" />
                {{ t('home') }}
            </Link>
            <Link
                href="/catalogo"
                class="mobile-tab"
                :class="{ 'is-active': currentPath.startsWith('/catalogo') }"
            >
                <LayoutGrid class="size-5" />
                {{ t('categories') }}
            </Link>
            <button type="button" class="mobile-tab" @click="openCart">
                <span class="relative">
                    <ShoppingCart class="size-5" />
                    <span
                        v-if="mounted && cartCount > 0"
                        class="absolute -top-1.5 -right-2 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[9px] leading-none font-bold text-primary-foreground"
                    >
                        {{ cartCount }}
                    </span>
                </span>
                {{ t('cart') }}
            </button>
            <Link
                href="/favoritos"
                class="mobile-tab"
                :class="{ 'is-active': currentPath === '/favoritos' }"
            >
                <span class="relative">
                    <Heart class="size-5" />
                    <span
                        v-if="mounted && wishlistCount > 0"
                        class="absolute -top-1.5 -right-2 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[9px] leading-none font-bold text-primary-foreground"
                    >
                        {{ wishlistCount }}
                    </span>
                </span>
                {{ t('favorites') }}
            </Link>
            <Link
                :href="accountHref"
                class="mobile-tab"
                :class="{ 'is-active': accountActive }"
            >
                <component :is="accountIcon" class="size-5" />
                {{ user ? (isStaff ? t('panel') : t('account')) : t('login') }}
            </Link>
        </nav>

        <footer
            class="bg-[hsl(224_36%_10%)] text-white"
            :class="currentPath === '/' ? 'mt-0' : 'mt-16'"
        >
            <div
                class="mx-auto grid max-w-[1400px] gap-10 px-6 py-14 text-sm md:grid-cols-4 lg:px-8"
            >
                <div class="md:col-span-2">
                    <div class="mb-3 flex items-center gap-2.5">
                        <span
                            class="grid size-9 place-items-center rounded-xl bg-primary font-display text-base font-extrabold text-primary-foreground"
                        >
                            {{ (store.name || 'M').charAt(0).toUpperCase() }}
                        </span>
                        <h4 class="font-display text-xl font-extrabold">
                            {{ store.name }}
                        </h4>
                    </div>
                    <p class="max-w-md leading-relaxed text-white/60">
                        {{ store.tagline }}
                    </p>
                    <a
                        v-if="whatsappHref"
                        :href="whatsappHref"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 font-medium text-white transition hover:bg-white/15"
                    >
                        <MessageCircle class="size-4" />
                        {{ t('contact_whatsapp') }}
                    </a>
                </div>
                <div>
                    <h4
                        class="mb-4 text-xs font-semibold tracking-wider text-white/45 uppercase"
                    >
                        {{ t('contact') }}
                    </h4>
                    <ul class="space-y-2.5 text-white/75">
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
                            class="flex items-center gap-2 text-white/60"
                        >
                            <Clock class="size-4 text-white/40" />
                            {{ store.hours }}
                        </li>
                    </ul>
                </div>
                <div>
                    <h4
                        class="mb-4 text-xs font-semibold tracking-wider text-white/45 uppercase"
                    >
                        {{ t('navigation') }}
                    </h4>
                    <ul class="space-y-2.5 text-white/75">
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
                        <li>
                            <Link
                                href="/catalogo?sort=discount"
                                class="transition hover:text-white"
                            >
                                Ofertas
                            </Link>
                        </li>
                        <li>
                            <Link
                                href="/favoritos"
                                class="transition hover:text-white"
                            >
                                {{ t('favorites') }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-white/10">
                <div
                    class="mx-auto flex max-w-[1400px] flex-wrap items-center justify-between gap-2 px-6 py-4 text-xs text-white/40 lg:px-8"
                >
                    <span>
                        © {{ new Date().getFullYear() }} {{ store.name }}.
                        {{ t('rights_reserved') }}
                        <Link href="/privacidad" class="ml-3 hover:text-white"
                            >Privacidad</Link
                        >
                        <Link href="/terminos" class="ml-3 hover:text-white"
                            >Términos</Link
                        >
                    </span>
                    <span v-if="store.exchange_rate" class="tabular">
                        Precios en Bs · TC
                        {{ formatRate(store.exchange_rate) }}
                    </span>
                </div>
            </div>
        </footer>
    </div>
</template>

<style>
/* El contenedor storefront hereda los tokens definidos en app.css para modo
   claro (:root) y modo oscuro (.dark), aportando sombras y estilos del tab móvil. */
.storefront {
    --shadow-soft:
        0 1px 2px hsl(224 32% 12% / 0.04),
        0 2px 8px -2px hsl(224 32% 12% / 0.06);
    --shadow-lift:
        0 2px 4px hsl(224 32% 12% / 0.04),
        0 16px 32px -12px hsl(224 32% 12% / 0.16);
}

.dark .storefront {
    --shadow-soft:
        0 1px 3px hsl(0 0% 0% / 0.3), 0 2px 8px -2px hsl(0 0% 0% / 0.4);
    --shadow-lift:
        0 2px 6px hsl(0 0% 0% / 0.4), 0 16px 32px -12px hsl(0 0% 0% / 0.6);
}

.storefront .mobile-tab {
    display: flex;
    flex: 1 1 0%;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.125rem;
    padding: 0.5rem 0;
    font-size: 11px;
    font-weight: 500;
    color: var(--muted-foreground);
    position: relative;
}
.storefront .mobile-tab.is-active {
    color: var(--primary);
}
.storefront .mobile-tab.is-active::before {
    content: '';
    position: absolute;
    top: 0;
    left: 30%;
    right: 30%;
    height: 2px;
    border-radius: 0 0 2px 2px;
    background: var(--primary);
}
</style>

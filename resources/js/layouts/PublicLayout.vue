<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Globe,
    LayoutDashboard,
    LogIn,
    LogOut,
    Mail,
    MapPin,
    Menu,
    Phone,
    Search,
    ShoppingCart,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CartDrawer from '@/components/catalog/CartDrawer.vue';
import WhatsAppFab from '@/components/catalog/WhatsAppFab.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
const roles = computed(() => (page.props.auth?.roles ?? []) as string[]);
const isStaff = computed(
    () => roles.value.includes('admin') || roles.value.includes('vendedor'),
);
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

const { t, locale } = useTranslations();
const switchLocale = (l: 'es' | 'en') => {
    if (l !== locale.value) {
        window.location.href = `/locale/${l}`;
    }
};

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
            href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        />
        <link v-if="store.favicon_url" rel="icon" :href="store.favicon_url" />
    </Head>

    <div
        class="relative min-h-screen overflow-x-hidden bg-background text-foreground"
    >
        <!-- Decorativos de fondo (glow esmeralda + ámbar) -->
        <div
            aria-hidden="true"
            class="pointer-events-none fixed inset-0 -z-10 bg-[linear-gradient(180deg,hsl(155_24%_97%)_0%,hsl(155_24%_94%)_42%,hsl(0_0%_100%)_100%)] dark:bg-[linear-gradient(180deg,hsl(160_30%_5%)_0%,hsl(160_30%_7%)_55%,hsl(160_30%_5%)_100%)]"
        ></div>

        <!-- Topbar de contacto -->
        <div
            v-if="store.whatsapp || store.email"
            class="hidden border-b border-emerald-900/20 bg-emerald-950 text-[12px] text-white md:block"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-1.5"
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
                        class="inline-flex items-center gap-1 transition hover:text-accent2"
                    >
                        <Phone class="size-3" /> {{ store.whatsapp }}
                    </a>
                    <a
                        v-if="store.email"
                        :href="`mailto:${store.email}`"
                        class="inline-flex items-center gap-1 transition hover:text-accent2"
                    >
                        <Mail class="size-3" /> {{ store.email }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Header sticky glassy -->
        <header
            class="sticky top-0 z-30 border-b border-border/80 bg-white/92 shadow-[0_1px_0_hsl(160_30%_8%/0.04),0_12px_30px_-28px_hsl(160_30%_8%/0.35)] backdrop-blur-xl transition-all dark:bg-background/88"
        >
            <div
                class="mx-auto flex h-18 max-w-7xl items-center gap-3 px-4 py-3 md:gap-4"
            >
                <Link
                    href="/"
                    class="group flex shrink-0 items-center gap-2 transition"
                >
                    <img
                        v-if="store.logo_url"
                        :src="store.logo_url"
                        :alt="store.name"
                        class="h-9 w-auto max-w-[160px] object-contain transition group-hover:scale-[1.02]"
                    />
                    <template v-else>
                        <span
                            class="gradient-brand glow-brand grid h-9 w-9 place-items-center rounded-lg text-white shadow-md"
                        >
                            <Sparkles class="size-4" />
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
                        class="rounded-full px-4 py-2 text-sm font-semibold transition"
                        :class="
                            currentPath === '/'
                                ? 'bg-brand text-white shadow-sm shadow-brand/20'
                                : 'text-foreground/75 hover:bg-brand/10 hover:text-brand'
                        "
                    >
                        {{ t('home') }}
                    </Link>
                    <Link
                        href="/catalogo"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition"
                        :class="
                            currentPath.startsWith('/catalogo')
                                ? 'bg-brand text-white shadow-sm shadow-brand/20'
                                : 'text-foreground/75 hover:bg-brand/10 hover:text-brand'
                        "
                    >
                        {{ t('catalog') }}
                    </Link>
                </nav>

                <form
                    @submit.prevent="doSearch"
                    class="relative ml-auto hidden max-w-sm flex-1 md:block"
                >
                    <Search
                        class="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="q"
                        :placeholder="t('search_placeholder')"
                        class="h-11 rounded-full border-border/80 bg-white pl-10 shadow-inner shadow-black/[0.02] placeholder:text-muted-foreground/80 focus-visible:border-brand focus-visible:bg-white dark:bg-card"
                    />
                </form>

                <div class="ml-auto flex items-center gap-1 md:ml-2 md:gap-2">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="relative rounded-full hover:bg-brand/10 hover:text-brand"
                        @click="openCart"
                        aria-label="Carrito"
                    >
                        <ShoppingCart class="size-5" />
                        <span
                            v-if="cartCount > 0"
                            class="animate-pulse-soft absolute -top-1 -right-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-accent2 px-1 text-[10px] leading-none font-bold text-accent2-foreground shadow-md"
                        >
                            {{ cartCount }}
                        </span>
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="gap-1 rounded-full hover:bg-brand/10 hover:text-brand"
                            >
                                <Globe class="size-4" />
                                <span class="uppercase">{{ locale }}</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="switchLocale('es')">
                                🇪🇸 Español
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="switchLocale('en')">
                                🇺🇸 English
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <template v-if="user">
                        <Button
                            v-if="isStaff"
                            variant="outline"
                            size="sm"
                            as-child
                            class="rounded-full border-border bg-white font-semibold shadow-sm hover:border-brand/40 hover:bg-brand/5 dark:bg-card"
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
                            class="rounded-full hover:bg-brand/10 hover:text-brand"
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
                        class="gradient-brand glow-brand hidden rounded-full border-transparent text-white hover:opacity-90 sm:inline-flex"
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
                                    class="rounded-md px-3 py-2 text-sm font-medium hover:bg-brand/10 hover:text-brand"
                                >
                                    {{ t('home') }}
                                </Link>
                                <Link
                                    href="/catalogo"
                                    class="rounded-md px-3 py-2 text-sm font-medium hover:bg-brand/10 hover:text-brand"
                                >
                                    {{ t('catalog') }}
                                </Link>
                            </nav>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </header>

        <transition
            enter-active-class="transition duration-300"
            enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-300"
            leave-to-class="-translate-y-2 opacity-0"
        >
            <div
                v-if="showFlash && flash.success"
                class="sticky top-20 z-20 mx-auto max-w-7xl px-4 pt-3"
            >
                <div
                    class="rounded-xl border border-brand/30 bg-brand/10 px-4 py-3 text-sm font-medium text-brand shadow-lg backdrop-blur"
                >
                    ✨ {{ flash.success }}
                </div>
            </div>
        </transition>

        <main>
            <slot />
        </main>

        <CartDrawer />
        <WhatsAppFab />

        <footer class="mt-0">
            <div
                class="bg-[linear-gradient(135deg,hsl(160_44%_13%)_0%,hsl(159_78%_24%)_72%,hsl(38_92%_42%)_100%)] text-white"
            >
                <div
                    class="mx-auto grid max-w-7xl gap-10 px-4 py-14 text-sm md:grid-cols-4"
                >
                    <div class="md:col-span-2">
                        <h4 class="mb-2 font-display text-2xl font-bold">
                            {{ store.name }}
                        </h4>
                        <p class="max-w-md text-white/80">
                            {{ store.tagline }}
                        </p>
                    </div>
                    <div>
                        <h4
                            class="mb-3 text-xs font-bold tracking-wider text-white/70 uppercase"
                        >
                            {{ t('contact') }}
                        </h4>
                        <ul class="space-y-2 text-white/90">
                            <li
                                v-if="store.whatsapp"
                                class="flex items-center gap-2"
                            >
                                <Phone class="size-4 text-accent2" />
                                {{ store.whatsapp }}
                            </li>
                            <li
                                v-if="store.email"
                                class="flex items-center gap-2"
                            >
                                <Mail class="size-4 text-accent2" />
                                {{ store.email }}
                            </li>
                            <li
                                v-if="store.address"
                                class="flex items-start gap-2"
                            >
                                <MapPin class="mt-0.5 size-4 text-accent2" />
                                <span>{{ store.address }}</span>
                            </li>
                            <li
                                v-if="store.hours"
                                class="flex items-center gap-2 opacity-80"
                            >
                                {{ t('hours') }}: {{ store.hours }}
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h4
                            class="mb-3 text-xs font-bold tracking-wider text-white/70 uppercase"
                        >
                            {{ t('navigation') }}
                        </h4>
                        <ul class="space-y-2 text-white/90">
                            <li>
                                <Link
                                    href="/"
                                    class="transition hover:text-accent2"
                                >
                                    {{ t('home') }}
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/catalogo"
                                    class="transition hover:text-accent2"
                                >
                                    {{ t('catalog') }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
                <div
                    class="border-t border-white/15 py-3 text-center text-xs text-white/70"
                >
                    © {{ new Date().getFullYear() }} {{ store.name }}.
                    {{ t('rights_reserved') }}
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.h-18 {
    height: 4.5rem;
}
</style>

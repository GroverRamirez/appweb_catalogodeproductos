<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    MessageCircle,
    Package,
    ShieldCheck,
    Sparkles,
    Truck,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import HeroCarousel from '@/components/catalog/HeroCarousel.vue';
import ProductCard from '@/components/catalog/ProductCard.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { imageUrl } from '@/lib/catalog';
import type { CatalogProduct, StoreSettings } from '@/lib/catalog';

defineOptions({ layout: PublicLayout });

type Product = CatalogProduct;
type Category = {
    id: number;
    name: string;
    slug: string;
    image: string | null;
};
type Banner = {
    id: number;
    title: string | null;
    subtitle: string | null;
    image_url: string;
    link: string | null;
    cta_text: string | null;
};

type Seo = {
    title: string;
    description: string | null;
    canonical: string;
    og_image: string | null;
};

defineProps<{
    banners: Banner[];
    featured: Product[];
    newest: Product[];
    categories: Category[];
    seo: Seo;
}>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const { t } = useTranslations();

const perks = [
    {
        icon: Truck,
        title: 'Despacho rápido',
        text: 'Coordinamos entrega ese mismo día en zona local.',
    },
    {
        icon: ShieldCheck,
        title: 'Garantía real',
        text: 'Productos respaldados. Si algo falla, lo cambiamos.',
    },
    {
        icon: MessageCircle,
        title: 'Atención humana',
        text: 'Te escribimos por WhatsApp y resolvemos al toque.',
    },
    {
        icon: Package,
        title: 'Stock siempre fresco',
        text: 'Renovamos catálogo cada semana con lo nuevo.',
    },
];
</script>

<template>
    <div class="catalog-home">
        <SeoHead
            :title="seo.title"
            :description="seo.description ?? undefined"
            :canonical="seo.canonical"
            :og-image="seo.og_image ?? undefined"
        />

        <!-- Carrusel de banners (si existen) -->
        <HeroCarousel v-if="banners.length" :banners="banners" />

        <!-- Hero fallback espectacular cuando no hay banners -->
        <section v-else class="relative overflow-hidden border-b">
            <div class="gradient-brand-soft absolute inset-0"></div>
            <div
                aria-hidden="true"
                class="pattern-dots pointer-events-none absolute inset-0 text-brand/10"
            ></div>
            <div
                class="relative mx-auto flex max-w-7xl flex-col items-center gap-7 px-4 py-20 text-center md:py-28"
            >
                <span
                    class="reveal inline-flex items-center gap-2 rounded-full border border-brand/30 bg-background/80 px-4 py-1.5 text-xs font-semibold text-brand shadow-sm backdrop-blur"
                >
                    <Sparkles class="animate-pulse-soft size-3.5" />
                    {{ t('featured_products') }}
                </span>
                <h1
                    class="reveal reveal-d1 max-w-4xl font-display text-4xl leading-[1.05] font-extrabold tracking-tight md:text-6xl lg:text-7xl"
                >
                    <span class="gradient-text">{{ store.name }}</span>
                </h1>
                <p
                    class="reveal reveal-d2 max-w-2xl text-base text-muted-foreground md:text-lg"
                >
                    {{ store.tagline }}
                </p>
                <div
                    class="reveal reveal-d3 flex flex-wrap justify-center gap-3"
                >
                    <Button
                        as-child
                        size="lg"
                        class="gradient-brand glow-brand rounded-full border-transparent text-white hover:opacity-95"
                    >
                        <Link href="/catalogo">
                            {{ t('view_catalog') }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        v-if="store.whatsapp"
                        as-child
                        variant="outline"
                        size="lg"
                        class="rounded-full border-brand/30 hover:bg-brand/5"
                    >
                        <a
                            :href="`https://wa.me/${store.whatsapp.replace(/[^0-9]/g, '')}`"
                            target="_blank"
                        >
                            <MessageCircle class="size-4" />
                            {{ t('contact_whatsapp') }}
                        </a>
                    </Button>
                </div>
            </div>
        </section>

        <!-- Perks / value props -->
        <section class="border-y border-border/70 bg-white dark:bg-card/70">
            <div
                class="mx-auto grid max-w-7xl gap-4 px-4 py-8 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div
                    v-for="(p, i) in perks"
                    :key="p.title"
                    :class="['reveal', `reveal-d${(i % 4) + 1}`]"
                    class="flex items-start gap-4 rounded-2xl border border-border/80 bg-background/70 p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-brand/30 hover:bg-white hover:shadow-md dark:bg-background/60 dark:hover:bg-card"
                >
                    <span
                        class="grid size-12 shrink-0 place-items-center rounded-xl bg-brand text-white shadow-sm shadow-brand/20"
                    >
                        <component :is="p.icon" class="size-5" />
                    </span>
                    <div>
                        <h3 class="text-sm font-bold text-foreground">
                            {{ p.title }}
                        </h3>
                        <p
                            class="mt-1 text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ p.text }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Categorías -->
        <section
            v-if="categories.length"
            class="mx-auto max-w-7xl px-4 py-14 md:py-16"
        >
            <div class="mb-8 flex flex-wrap items-end justify-between gap-3">
                <div class="reveal">
                    <p
                        class="mb-2 text-xs font-extrabold tracking-widest text-brand uppercase"
                    >
                        Explora
                    </p>
                    <h2
                        class="font-display text-3xl font-extrabold text-foreground md:text-4xl"
                    >
                        {{ t('categories') }}
                    </h2>
                </div>
                <Link
                    href="/catalogo"
                    class="group inline-flex items-center gap-1 rounded-full border border-brand/20 bg-white px-4 py-2 text-sm font-bold text-brand shadow-sm transition hover:border-brand/40 hover:bg-brand/5 dark:bg-card"
                >
                    {{ t('see_all') }}
                    <ArrowRight
                        class="size-3 transition-transform group-hover:translate-x-1"
                    />
                </Link>
            </div>
            <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <Link
                    v-for="(c, i) in categories"
                    :key="c.id"
                    :href="`/catalogo?category=${c.slug}`"
                    :class="['reveal', `reveal-d${(i % 4) + 1}`]"
                    class="group relative flex min-h-36 flex-col items-center justify-center overflow-hidden rounded-2xl border border-border/80 bg-white p-5 text-center shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-brand/45 hover:shadow-lg hover:shadow-brand/10 dark:bg-card"
                >
                    <div
                        class="gradient-brand-soft absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                    ></div>
                    <div class="relative z-10 flex flex-col items-center">
                        <img
                            v-if="imageUrl(c.image)"
                            :src="imageUrl(c.image)!"
                            :alt="c.name"
                            class="mb-3 size-14 rounded-xl border border-border/60 object-cover shadow-sm transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3"
                        />
                        <div
                            v-else
                            class="gradient-brand mb-3 grid size-14 place-items-center rounded-xl text-xl font-extrabold text-white shadow-md transition-transform duration-500 group-hover:scale-110 group-hover:rotate-3"
                        >
                            {{ c.name.charAt(0) }}
                        </div>
                        <span
                            class="font-display text-sm leading-tight font-bold text-foreground"
                        >
                            {{ c.name }}
                        </span>
                    </div>
                    <div
                        class="gradient-brand absolute bottom-0 left-1/2 h-[2px] w-0 -translate-x-1/2 transition-all duration-500 group-hover:w-3/4"
                    ></div>
                </Link>
            </div>
        </section>

        <!-- Destacados con fondo decorativo -->
        <section
            v-if="featured.length"
            class="relative overflow-hidden border-y border-border/70 bg-[linear-gradient(135deg,hsl(155_26%_92%)_0%,hsl(0_0%_100%)_46%,hsl(42_74%_93%)_100%)] dark:bg-[linear-gradient(135deg,hsl(160_30%_7%)_0%,hsl(160_30%_5%)_58%,hsl(160_24%_10%)_100%)]"
        >
            <div
                aria-hidden="true"
                class="pattern-dots absolute inset-0 text-brand/8 dark:text-brand/5"
            ></div>
            <div class="relative mx-auto max-w-7xl px-4 py-16 md:py-20">
                <div
                    class="mb-8 flex flex-wrap items-end justify-between gap-3"
                >
                    <div class="reveal">
                        <p
                            class="mb-2 inline-flex items-center gap-1 text-xs font-extrabold tracking-widest text-brand uppercase"
                        >
                            <Sparkles class="size-3" /> Lo mejor
                        </p>
                        <h2
                            class="font-display text-3xl font-extrabold text-foreground md:text-4xl"
                        >
                            {{ t('featured_products') }}
                        </h2>
                    </div>
                    <Link
                        href="/catalogo?sort=newest"
                        class="group inline-flex items-center gap-1 rounded-full border border-brand/20 bg-white px-4 py-2 text-sm font-bold text-brand shadow-sm transition hover:border-brand/40 hover:bg-brand/5 dark:bg-card"
                    >
                        {{ t('see_more') }}
                        <ArrowRight
                            class="size-3 transition-transform group-hover:translate-x-1"
                        />
                    </Link>
                </div>
                <div
                    class="grid gap-5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:gap-6"
                >
                    <div
                        v-for="(p, i) in featured"
                        :key="p.id"
                        :class="['reveal', `reveal-d${(i % 4) + 1}`]"
                    >
                        <ProductCard :product="p" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Nuevos -->
        <section
            v-if="newest.length"
            class="mx-auto max-w-7xl px-4 py-16 md:py-20"
        >
            <div class="mb-8 flex flex-wrap items-end justify-between gap-3">
                <div class="reveal">
                    <p
                        class="mb-2 inline-flex items-center gap-1 text-xs font-extrabold tracking-widest text-brand uppercase"
                    >
                        <Zap class="size-3" /> Acabaditos de llegar
                    </p>
                    <h2
                        class="font-display text-3xl font-extrabold text-foreground md:text-4xl"
                    >
                        {{ t('newest') }}
                    </h2>
                </div>
                <Link
                    href="/catalogo?sort=newest"
                    class="group inline-flex items-center gap-1 rounded-full border border-brand/20 bg-white px-4 py-2 text-sm font-bold text-brand shadow-sm transition hover:border-brand/40 hover:bg-brand/5 dark:bg-card"
                >
                    {{ t('see_more') }}
                    <ArrowRight
                        class="size-3 transition-transform group-hover:translate-x-1"
                    />
                </Link>
            </div>
            <div
                class="grid gap-5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:gap-6"
            >
                <div
                    v-for="(p, i) in newest"
                    :key="p.id"
                    :class="['reveal', `reveal-d${(i % 4) + 1}`]"
                >
                    <ProductCard :product="p" />
                </div>
            </div>
        </section>

        <!-- CTA final -->
        <section
            class="relative overflow-hidden border-y border-emerald-900/40 bg-[linear-gradient(135deg,hsl(160_44%_10%)_0%,hsl(160_40%_13%)_56%,hsl(159_78%_20%)_100%)] text-white"
        >
            <div
                aria-hidden="true"
                class="pattern-dots absolute inset-0 text-white/8"
            ></div>
            <div
                class="relative mx-auto flex max-w-5xl flex-col items-center gap-6 px-4 py-20 text-center md:py-24"
            >
                <h2
                    class="reveal max-w-3xl font-display text-3xl leading-tight font-extrabold md:text-5xl"
                >
                    ¿Listo para encontrar lo que buscas?
                </h2>
                <p class="reveal reveal-d1 max-w-xl text-white/72">
                    Más de mil productos seleccionados. Explora nuestro catálogo
                    o escríbenos por WhatsApp.
                </p>
                <div
                    class="reveal reveal-d2 flex flex-wrap justify-center gap-3"
                >
                    <Button
                        as-child
                        size="lg"
                        class="gradient-brand glow-brand rounded-full border-transparent text-white"
                    >
                        <Link href="/catalogo">
                            {{ t('view_catalog') }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        v-if="store.whatsapp"
                        as-child
                        variant="outline"
                        size="lg"
                        class="rounded-full border-background/30 bg-background/10 text-background hover:bg-background/20"
                    >
                        <a
                            :href="`https://wa.me/${store.whatsapp.replace(/[^0-9]/g, '')}`"
                            target="_blank"
                        >
                            <MessageCircle class="size-4" />
                            WhatsApp
                        </a>
                    </Button>
                </div>
            </div>
        </section>
    </div>
</template>

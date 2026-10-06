<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    MessageCircle,
    Package,
    ShieldCheck,
    Truck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import HeroCarousel from '@/components/catalog/HeroCarousel.vue';
import ProductCard from '@/components/catalog/ProductCard.vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { imageUrl } from '@/lib/catalog';
import type { CatalogProduct, StoreSettings } from '@/lib/catalog';
import { categoryIcon } from '@/lib/categoryIcons';

defineOptions({ layout: PublicLayout });

type Product = CatalogProduct;
type Category = {
    id: number;
    name: string;
    slug: string;
    image: string | null;
    /** Foto de uno de sus productos, cuando la categoría no tiene imagen propia. */
    product_image: string | null;
    products_count: number;
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

/**
 * Foto de la tarjeta: manda la imagen de la categoría y, si no hay, se usa la
 * de uno de sus productos. Si tampoco hay, la tarjeta cae en el ícono.
 */
const categoryImage = (category: Category): string | null =>
    imageUrl(category.image) ?? imageUrl(category.product_image);

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

        <!-- Hero fallback cuando no hay banners -->
        <section v-else class="border-b border-border bg-muted/40">
            <div
                class="mx-auto flex max-w-[1400px] flex-col items-start gap-5 px-6 py-20 md:py-24 lg:px-8"
            >
                <h1
                    class="max-w-3xl font-display text-4xl leading-[1.1] font-bold tracking-tight md:text-5xl"
                >
                    {{ store.name }}
                </h1>
                <p class="max-w-2xl text-base text-muted-foreground md:text-lg">
                    {{ store.tagline }}
                </p>
                <div class="mt-1 flex flex-wrap gap-3">
                    <Button as-child size="lg" class="rounded-md">
                        <Link href="/catalogo">
                            {{ t('view_catalog') }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        v-if="store.whatsapp"
                        as-child
                        size="lg"
                        class="rounded-md bg-[#25D366] text-white shadow-sm shadow-[#25D366]/25 hover:bg-[#128C7E] hover:text-white"
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
        <section class="border-b border-border bg-card">
            <div
                class="mx-auto grid max-w-[1400px] gap-x-8 gap-y-5 px-6 py-7 sm:grid-cols-2 md:py-8 lg:grid-cols-4 lg:px-8"
            >
                <div
                    v-for="p in perks"
                    :key="p.title"
                    class="flex items-start gap-3"
                >
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-md bg-accent text-primary"
                    >
                        <component :is="p.icon" class="size-5" />
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">
                            {{ p.title }}
                        </h3>
                        <p
                            class="mt-0.5 text-xs leading-relaxed text-muted-foreground"
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
            class="border-b border-border/70 bg-gradient-to-b from-background via-muted/20 to-background"
        >
            <div class="mx-auto max-w-[1400px] px-6 py-10 lg:px-8 lg:py-12">
                <div
                    class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div class="max-w-2xl">
                        <span
                            class="mb-2 inline-flex rounded-full border border-primary/15 bg-primary/5 px-3 py-1 text-xs font-semibold tracking-wide text-primary uppercase"
                        >
                            Explora el catálogo
                        </span>
                        <h2
                            class="font-display text-2xl font-bold tracking-tight text-foreground md:text-3xl"
                        >
                            Compra por categoría
                        </h2>
                        <p
                            class="mt-2 text-sm leading-relaxed text-muted-foreground md:text-base"
                        >
                            Encuentra rápidamente los productos que necesitas
                            para tu hogar, oficina o negocio.
                        </p>
                    </div>
                    <Link
                        href="/catalogo"
                        class="group inline-flex w-fit items-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-sm font-semibold text-foreground shadow-sm transition hover:border-primary/30 hover:text-primary hover:shadow-md"
                    >
                        {{ t('see_all') }}
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                        />
                    </Link>
                </div>

                <div
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-6"
                >
                    <Link
                        v-for="c in categories"
                        :key="c.id"
                        :href="`/catalogo?category=${c.slug}`"
                        class="group relative flex min-h-44 flex-col overflow-hidden rounded-2xl border border-border/70 bg-card p-3 shadow-sm transition duration-300 hover:-translate-y-1 hover:border-primary/25 hover:shadow-xl hover:shadow-primary/5 sm:min-h-52 sm:p-4"
                    >
                        <div
                            class="relative grid min-h-28 flex-1 place-items-center overflow-hidden rounded-xl p-4 sm:min-h-32"
                        >
                            <span
                                class="absolute top-2 right-2 z-20 grid size-8 place-items-center rounded-full border border-white/70 bg-white/80 text-muted-foreground shadow-sm backdrop-blur transition group-hover:border-primary group-hover:bg-primary group-hover:text-primary-foreground dark:border-white/10 dark:bg-background/70"
                                aria-hidden="true"
                            >
                                <ArrowRight
                                    class="size-3.5 transition-transform group-hover:translate-x-0.5"
                                />
                            </span>
                            <img
                                v-if="categoryImage(c)"
                                :src="categoryImage(c)!"
                                :alt="c.name"
                                loading="lazy"
                                class="relative z-10 h-24 w-full object-contain transition-transform duration-300 group-hover:scale-110 sm:h-28"
                            />
                            <component
                                v-else
                                :is="categoryIcon(c.slug)"
                                class="relative z-10 size-20 p-3 text-primary/55 transition-transform duration-300 group-hover:scale-110"
                            />
                        </div>

                        <div class="px-1 pt-3">
                            <h3
                                class="line-clamp-2 text-sm leading-tight font-bold text-foreground sm:text-base"
                            >
                                {{ c.name }}
                            </h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ c.products_count }}
                                {{
                                    c.products_count === 1
                                        ? 'producto'
                                        : 'productos'
                                }}
                            </p>
                        </div>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Destacados -->
        <section
            v-if="featured.length"
            class="border-t border-border bg-muted/40"
        >
            <div class="mx-auto max-w-[1400px] px-6 py-10 lg:px-8">
                <div class="mb-4 flex items-end justify-between gap-3">
                    <h2
                        class="font-display text-2xl font-bold tracking-tight text-foreground md:text-3xl"
                    >
                        {{ t('featured_products') }}
                    </h2>
                    <Link
                        href="/catalogo?sort=newest"
                        class="group inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                    >
                        {{ t('see_more') }}
                        <ArrowRight class="size-3.5" />
                    </Link>
                </div>
                <div
                    class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                >
                    <ProductCard
                        v-for="p in featured"
                        :key="p.id"
                        :product="p"
                    />
                </div>
            </div>
        </section>

        <!-- Nuevos -->
        <section
            v-if="newest.length"
            class="mx-auto max-w-[1400px] px-6 py-10 lg:px-8"
        >
            <div class="mb-4 flex items-end justify-between gap-3">
                <h2
                    class="font-display text-2xl font-bold tracking-tight text-foreground md:text-3xl"
                >
                    {{ t('newest') }}
                </h2>
                <Link
                    href="/catalogo?sort=newest"
                    class="group inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                >
                    {{ t('see_more') }}
                    <ArrowRight class="size-3.5" />
                </Link>
            </div>
            <div
                class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
            >
                <ProductCard v-for="p in newest" :key="p.id" :product="p" />
            </div>
        </section>

        <!-- CTA final -->
        <section
            class="border-t border-border bg-[hsl(222_33%_13%)] text-white"
        >
            <div
                class="mx-auto flex max-w-5xl flex-col items-center gap-3 px-4 py-10 text-center md:py-12"
            >
                <h2
                    class="max-w-2xl font-display text-2xl leading-tight font-bold md:text-3xl"
                >
                    ¿Listo para encontrar lo que buscas?
                </h2>
                <p class="max-w-xl text-white/60">
                    Explora nuestro catálogo o escríbenos por WhatsApp.
                </p>
                <div class="flex flex-wrap justify-center gap-3">
                    <Button as-child size="lg" class="rounded-md">
                        <Link href="/catalogo">
                            {{ t('view_catalog') }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        v-if="store.whatsapp"
                        as-child
                        size="lg"
                        class="rounded-md bg-[#25D366] text-white shadow-sm shadow-[#25D366]/25 hover:bg-[#128C7E] hover:text-white"
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

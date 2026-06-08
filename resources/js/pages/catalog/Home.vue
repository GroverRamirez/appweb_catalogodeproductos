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

        <!-- Hero fallback cuando no hay banners -->
        <section v-else class="border-b border-border bg-muted/40">
            <div
                class="mx-auto flex max-w-7xl flex-col items-start gap-5 px-4 py-20 md:py-24"
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
                        variant="outline"
                        size="lg"
                        class="rounded-md"
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
        <section class="border-b border-border bg-background">
            <div
                class="mx-auto grid max-w-7xl gap-x-8 gap-y-6 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4"
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
        <section v-if="categories.length" class="mx-auto max-w-7xl px-4 py-14">
            <div class="mb-6 flex items-end justify-between gap-3">
                <h2
                    class="font-display text-2xl font-bold tracking-tight text-foreground md:text-3xl"
                >
                    {{ t('categories') }}
                </h2>
                <Link
                    href="/catalogo"
                    class="group inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                >
                    {{ t('see_all') }}
                    <ArrowRight class="size-3.5" />
                </Link>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <Link
                    v-for="c in categories"
                    :key="c.id"
                    :href="`/catalogo?category=${c.slug}`"
                    class="group flex min-h-32 flex-col items-center justify-center gap-3 rounded-lg border border-border bg-card p-5 text-center transition-colors hover:border-primary/40 hover:bg-accent/40"
                >
                    <img
                        v-if="imageUrl(c.image)"
                        :src="imageUrl(c.image)!"
                        :alt="c.name"
                        class="size-12 rounded-md border border-border object-cover"
                    />
                    <div
                        v-else
                        class="grid size-12 place-items-center rounded-md bg-accent text-lg font-bold text-primary"
                    >
                        {{ c.name.charAt(0) }}
                    </div>
                    <span
                        class="text-sm leading-tight font-medium text-foreground"
                    >
                        {{ c.name }}
                    </span>
                </Link>
            </div>
        </section>

        <!-- Destacados -->
        <section
            v-if="featured.length"
            class="border-t border-border bg-muted/40"
        >
            <div class="mx-auto max-w-7xl px-4 py-14">
                <div class="mb-6 flex items-end justify-between gap-3">
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
                    class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4"
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
        <section v-if="newest.length" class="mx-auto max-w-7xl px-4 py-14">
            <div class="mb-6 flex items-end justify-between gap-3">
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
            <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
                <ProductCard v-for="p in newest" :key="p.id" :product="p" />
            </div>
        </section>

        <!-- CTA final -->
        <section
            class="border-t border-border bg-[hsl(222_33%_13%)] text-white"
        >
            <div
                class="mx-auto flex max-w-5xl flex-col items-center gap-5 px-4 py-16 text-center"
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
                        variant="outline"
                        size="lg"
                        class="rounded-md border-white/25 bg-transparent text-white hover:bg-white/10 hover:text-white"
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

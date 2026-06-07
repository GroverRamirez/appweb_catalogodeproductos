<script setup lang="ts">
import { LayoutGrid, List, Sparkles } from 'lucide-vue-next';
import { ref } from 'vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import Filters from '@/components/catalog/Filters.vue';
import ProductCard from '@/components/catalog/ProductCard.vue';
import ProductRow from '@/components/catalog/ProductRow.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { CatalogProduct } from '@/lib/catalog';

defineOptions({ layout: PublicLayout });

type Product = CatalogProduct;

defineProps<{
    products: {
        data: Product[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: any;
    categories: {
        id: number;
        name: string;
        slug: string;
        parent_id: number | null;
    }[];
    brands: { id: number; name: string; slug: string }[];
    seo: {
        title: string;
        description: string | null;
        canonical: string;
        og_image: string | null;
        noindex?: boolean;
    };
}>();

const initial: 'grid' | 'list' =
    typeof window !== 'undefined' &&
    (localStorage.getItem('catalog_view') as 'grid' | 'list') === 'list'
        ? 'list'
        : 'grid';
const view = ref<'grid' | 'list'>(initial);

const setView = (v: 'grid' | 'list') => {
    view.value = v;

    if (typeof window !== 'undefined') {
        localStorage.setItem('catalog_view', v);
    }
};
</script>

<template>
    <SeoHead
        :title="seo.title"
        :description="seo.description ?? undefined"
        :canonical="seo.canonical"
        :og-image="seo.og_image ?? undefined"
        :noindex="seo.noindex"
    />

    <!-- Sub-hero del catálogo -->
    <section class="relative overflow-hidden border-b">
        <div class="gradient-brand-soft absolute inset-0"></div>
        <div class="relative mx-auto max-w-7xl px-4 py-10 md:py-14">
            <p
                class="mb-2 inline-flex items-center gap-1 text-xs font-bold tracking-widest text-brand uppercase"
            >
                <Sparkles class="size-3" /> Explora nuestro catálogo
            </p>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1
                        class="font-display text-3xl font-bold tracking-tight md:text-4xl"
                    >
                        Catálogo
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <span class="font-semibold text-foreground">{{
                            products.total
                        }}</span>
                        productos disponibles
                        <span v-if="filters.q">
                            para "<span class="font-semibold text-brand">{{
                                filters.q
                            }}</span
                            >"
                        </span>
                    </p>
                </div>

                <div
                    class="inline-flex rounded-full border bg-card p-1 shadow-sm"
                >
                    <Button
                        type="button"
                        size="sm"
                        :variant="view === 'grid' ? 'default' : 'ghost'"
                        :class="[
                            'rounded-full px-3',
                            view === 'grid' &&
                                'gradient-brand border-transparent text-white',
                        ]"
                        @click="setView('grid')"
                        aria-label="Vista de cuadrícula"
                    >
                        <LayoutGrid class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="view === 'list' ? 'default' : 'ghost'"
                        :class="[
                            'rounded-full px-3',
                            view === 'list' &&
                                'gradient-brand border-transparent text-white',
                        ]"
                        @click="setView('list')"
                        aria-label="Vista de lista"
                    >
                        <List class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-8">
        <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
            <div class="lg:sticky lg:top-24 lg:self-start">
                <Filters
                    :categories="categories"
                    :brands="brands"
                    :filters="filters"
                />
            </div>

            <div>
                <template v-if="products.data.length">
                    <div
                        v-if="view === 'grid'"
                        class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                    >
                        <div
                            v-for="(p, i) in products.data"
                            :key="p.id"
                            :class="['reveal', `reveal-d${(i % 4) + 1}`]"
                        >
                            <ProductCard :product="p" />
                        </div>
                    </div>
                    <div v-else class="flex flex-col gap-3">
                        <div
                            v-for="(p, i) in products.data"
                            :key="p.id"
                            :class="['reveal', `reveal-d${(i % 4) + 1}`]"
                        >
                            <ProductRow :product="p" />
                        </div>
                    </div>
                </template>
                <div
                    v-else
                    class="rounded-2xl border-2 border-dashed bg-card/50 p-16 text-center"
                >
                    <div
                        class="mx-auto mb-3 grid size-14 place-items-center rounded-full bg-brand/10 text-brand"
                    >
                        <Sparkles class="size-6" />
                    </div>
                    <p class="font-semibold">
                        No encontramos productos con esos filtros.
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Prueba quitando algún filtro o cambia los términos.
                    </p>
                </div>

                <div class="mt-8">
                    <Pagination
                        :links="products.links"
                        :from="products.from"
                        :to="products.to"
                        :total="products.total"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

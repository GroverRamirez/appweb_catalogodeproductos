<script setup lang="ts">
import { LayoutGrid, List, SearchX } from 'lucide-vue-next';
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

    <!-- Encabezado del catálogo -->
    <section class="border-b border-border bg-muted/40">
        <div class="mx-auto max-w-7xl px-4 py-8 md:py-10">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1
                        class="font-display text-2xl font-bold tracking-tight md:text-3xl"
                    >
                        Catálogo
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <span class="font-medium text-foreground">{{
                            products.total
                        }}</span>
                        productos disponibles
                        <span v-if="filters.q">
                            para "<span class="font-medium text-foreground">{{
                                filters.q
                            }}</span
                            >"
                        </span>
                    </p>
                </div>

                <div
                    class="inline-flex rounded-md border border-border bg-card p-0.5"
                >
                    <Button
                        type="button"
                        size="sm"
                        :variant="view === 'grid' ? 'default' : 'ghost'"
                        class="rounded px-3"
                        @click="setView('grid')"
                        aria-label="Vista de cuadrícula"
                    >
                        <LayoutGrid class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="view === 'list' ? 'default' : 'ghost'"
                        class="rounded px-3"
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
                        class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-4"
                    >
                        <ProductCard
                            v-for="p in products.data"
                            :key="p.id"
                            :product="p"
                        />
                    </div>
                    <div v-else class="flex flex-col gap-3">
                        <ProductRow
                            v-for="p in products.data"
                            :key="p.id"
                            :product="p"
                        />
                    </div>
                </template>
                <div
                    v-else
                    class="rounded-lg border border-dashed border-border bg-muted/30 p-16 text-center"
                >
                    <div
                        class="mx-auto mb-3 grid size-12 place-items-center rounded-full bg-accent text-primary"
                    >
                        <SearchX class="size-6" />
                    </div>
                    <p class="font-medium text-foreground">
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

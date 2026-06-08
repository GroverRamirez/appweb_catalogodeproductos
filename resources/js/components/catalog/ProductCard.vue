<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Eye, ImageOff, ShoppingCart } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useCart } from '@/composables/useCart';
import { formatPrice, productMainImage } from '@/lib/catalog';
import type { CatalogProduct, StoreSettings } from '@/lib/catalog';
import QuickViewModal from './QuickViewModal.vue';

const props = defineProps<{ product: CatalogProduct }>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);

const image = computed(() => productMainImage(props.product));

const showSale = computed(
    () =>
        !!props.product.sale_price &&
        Number(props.product.sale_price) < Number(props.product.price),
);

const discountPct = computed(() => {
    if (!showSale.value) {
        return 0;
    }

    return Math.round(
        (1 - Number(props.product.sale_price) / Number(props.product.price)) *
            100,
    );
});

const outOfStock = computed(() => props.product.stock <= 0);
const lowStock = computed(
    () => props.product.stock > 0 && props.product.stock <= 5,
);

const isNew = computed(() => {
    if (!props.product.created_at) {
        return false;
    }

    const created = new Date(props.product.created_at).getTime();

    return Date.now() - created < 30 * 24 * 60 * 60 * 1000;
});

const quickOpen = ref(false);
const openQuick = (e: MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    quickOpen.value = true;
};

const { add: addToCart } = useCart();
const onAdd = (e: MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    addToCart({
        product_id: props.product.id,
        slug: props.product.slug,
        name: props.product.name,
        code: props.product.code,
        price: Number(props.product.sale_price ?? props.product.price),
        image: image.value,
    });
};
</script>

<template>
    <div class="group relative">
        <Link
            :href="`/catalogo/${product.slug}`"
            class="flex h-full flex-col overflow-hidden rounded-lg border border-border bg-card transition-colors hover:border-primary/40"
        >
            <!-- Imagen -->
            <div class="relative aspect-square overflow-hidden bg-muted">
                <img
                    v-if="image"
                    :src="image"
                    :alt="product.name"
                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                    loading="lazy"
                />
                <div
                    v-else
                    class="flex h-full w-full items-center justify-center text-muted-foreground"
                >
                    <ImageOff class="size-8 opacity-40" />
                </div>

                <!-- Badges -->
                <div
                    class="absolute top-2 left-2 flex flex-col items-start gap-1"
                >
                    <span
                        v-if="showSale"
                        class="rounded bg-destructive px-1.5 py-0.5 text-[11px] font-semibold text-destructive-foreground"
                    >
                        −{{ discountPct }}%
                    </span>
                    <span
                        v-else-if="isNew"
                        class="rounded bg-[hsl(222_33%_18%)] px-1.5 py-0.5 text-[11px] font-medium text-white"
                    >
                        Nuevo
                    </span>
                </div>

                <!-- Stock -->
                <div
                    v-if="store.show_stock && (outOfStock || lowStock)"
                    class="absolute top-2 right-2"
                >
                    <span
                        v-if="outOfStock"
                        class="rounded bg-foreground/80 px-1.5 py-0.5 text-[11px] font-medium text-background"
                    >
                        Sin stock
                    </span>
                    <span
                        v-else-if="lowStock"
                        class="rounded border border-border bg-background px-1.5 py-0.5 text-[11px] font-medium text-foreground"
                    >
                        Últimas {{ product.stock }}
                    </span>
                </div>

                <!-- CTAs en hover -->
                <div
                    class="absolute inset-x-2 bottom-2 flex gap-2 opacity-0 transition-opacity duration-200 group-hover:opacity-100"
                >
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        class="h-8 flex-1 rounded-md border border-border bg-background text-foreground hover:bg-muted"
                        @click="openQuick"
                    >
                        <Eye class="size-4" />
                        <span class="hidden lg:inline">Vista</span>
                    </Button>
                    <Button
                        v-if="!outOfStock"
                        type="button"
                        size="sm"
                        class="h-8 flex-1 rounded-md"
                        @click="onAdd"
                    >
                        <ShoppingCart class="size-4" />
                        <span class="hidden lg:inline">Agregar</span>
                    </Button>
                </div>
            </div>

            <!-- Contenido -->
            <div class="flex flex-1 flex-col gap-1 p-3">
                <p
                    v-if="product.category"
                    class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                >
                    {{ product.category.name }}
                </p>
                <h3
                    class="line-clamp-2 text-sm leading-snug font-medium text-foreground"
                >
                    {{ product.name }}
                </h3>
                <p v-if="product.brand" class="text-xs text-muted-foreground">
                    {{ product.brand.name }}
                </p>

                <div
                    v-if="store.show_prices"
                    class="mt-auto flex items-baseline gap-2 pt-2"
                >
                    <span class="text-base font-semibold text-foreground">
                        {{
                            formatPrice(
                                product.sale_price ?? product.price,
                                store.currency_symbol,
                            )
                        }}
                    </span>
                    <span
                        v-if="showSale"
                        class="text-xs text-muted-foreground line-through"
                    >
                        {{ formatPrice(product.price, store.currency_symbol) }}
                    </span>
                </div>
            </div>
        </Link>

        <QuickViewModal
            v-if="quickOpen"
            v-model:open="quickOpen"
            :product="product"
        />
    </div>
</template>

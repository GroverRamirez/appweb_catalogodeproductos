<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Star } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatPrice, productMainImage } from '@/lib/catalog';
import type { CatalogProduct, StoreSettings } from '@/lib/catalog';

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
</script>

<template>
    <Link
        :href="`/catalogo/${product.slug}`"
        class="group flex gap-4 rounded-lg border bg-card p-3 transition hover:border-primary/40 hover:shadow-md"
    >
        <div
            class="relative size-32 shrink-0 overflow-hidden rounded-md bg-muted sm:size-40"
        >
            <img
                v-if="image"
                :src="image"
                :alt="product.name"
                class="h-full w-full object-cover transition group-hover:scale-105"
                loading="lazy"
            />
            <div
                v-else
                class="flex h-full w-full items-center justify-center text-xs text-muted-foreground"
            >
                Sin imagen
            </div>
            <Badge
                v-if="showSale"
                variant="destructive"
                class="absolute top-1 right-1"
            >
                -{{ discountPct }}%
            </Badge>
        </div>

        <div class="flex flex-1 flex-col gap-1">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p
                        v-if="product.category"
                        class="text-xs text-muted-foreground"
                    >
                        {{ product.category.name }}
                        <span v-if="product.brand">
                            · {{ product.brand.name }}</span
                        >
                    </p>
                    <h3 class="text-base leading-snug font-semibold">
                        {{ product.name }}
                    </h3>
                </div>
                <Star
                    v-if="product.is_featured"
                    class="size-4 shrink-0 fill-amber-500 text-amber-500"
                />
            </div>

            <p
                v-if="product.short_description"
                class="line-clamp-2 text-sm text-muted-foreground"
            >
                {{ product.short_description }}
            </p>

            <div class="mt-auto flex items-end justify-between gap-2 pt-2">
                <div v-if="store.show_prices">
                    <div class="flex items-baseline gap-2">
                        <span class="text-lg font-semibold">
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
                            {{
                                formatPrice(
                                    product.price,
                                    store.currency_symbol,
                                )
                            }}
                        </span>
                    </div>
                    <p
                        v-if="store.show_stock"
                        class="text-xs"
                        :class="
                            outOfStock
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        "
                    >
                        {{
                            outOfStock
                                ? 'Sin stock'
                                : `${product.stock} disponibles`
                        }}
                    </p>
                </div>
                <Button size="sm" variant="outline">
                    Ver <ArrowRight class="size-3" />
                </Button>
            </div>
        </div>
    </Link>
</template>

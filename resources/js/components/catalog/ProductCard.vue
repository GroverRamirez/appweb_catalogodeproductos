<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Eye, Heart, ShoppingCart, Sparkles, Zap } from 'lucide-vue-next';
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
            class="relative flex h-full flex-col overflow-hidden rounded-2xl border border-border/80 bg-white shadow-[0_1px_2px_hsl(160_30%_8%/0.06),0_14px_36px_-30px_hsl(160_30%_8%/0.35)] transition-all duration-500 ease-out hover:-translate-y-1 hover:border-brand/45 hover:shadow-[0_22px_60px_-24px_hsl(160_30%_8%/0.35)] dark:bg-card"
        >
            <!-- Imagen con overlay -->
            <div
                class="relative aspect-[4/3] overflow-hidden bg-gradient-to-br from-muted via-white to-secondary dark:via-muted"
            >
                <img
                    v-if="image"
                    :src="image"
                    :alt="product.name"
                    class="h-full w-full object-cover transition-all duration-[800ms] ease-out group-hover:scale-110"
                    loading="lazy"
                />
                <div
                    v-else
                    class="flex h-full w-full items-center justify-center text-xs text-muted-foreground"
                >
                    <Sparkles class="size-8 opacity-30" />
                </div>

                <!-- Shine effect en hover -->
                <div
                    class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/30 to-transparent transition-transform duration-1000 group-hover:translate-x-full"
                ></div>

                <!-- Badges flotantes superiores -->
                <div
                    class="absolute top-3 left-3 flex flex-col items-start gap-1.5"
                >
                    <span
                        v-if="product.is_featured"
                        class="inline-flex items-center gap-1 rounded-full bg-amber-500/95 px-2.5 py-1 text-[10px] font-bold tracking-wider text-white uppercase shadow-lg backdrop-blur"
                    >
                        <Sparkles class="size-3" /> Top
                    </span>
                    <span
                        v-if="isNew"
                        class="inline-flex items-center gap-1 rounded-full bg-brand/95 px-2.5 py-1 text-[10px] font-bold tracking-wider text-white uppercase shadow-lg backdrop-blur"
                    >
                        <Zap class="size-3" /> Nuevo
                    </span>
                </div>

                <div
                    class="absolute top-3 right-3 flex flex-col items-end gap-1.5"
                >
                    <span
                        v-if="showSale"
                        class="rounded-full bg-rose-500/95 px-2.5 py-1 text-[11px] font-extrabold text-white shadow-lg backdrop-blur"
                    >
                        −{{ discountPct }}%
                    </span>
                </div>

                <!-- Stock badge inferior -->
                <div
                    v-if="store.show_stock && (outOfStock || lowStock)"
                    class="absolute bottom-3 left-3"
                >
                    <span
                        v-if="outOfStock"
                        class="rounded-full bg-foreground/85 px-2.5 py-1 text-[10px] font-bold tracking-wider text-background uppercase backdrop-blur"
                    >
                        Sin stock
                    </span>
                    <span
                        v-else-if="lowStock"
                        class="rounded-full bg-amber-500/95 px-2.5 py-1 text-[10px] font-bold tracking-wider text-white uppercase shadow backdrop-blur"
                    >
                        ¡Últimas {{ product.stock }}!
                    </span>
                </div>

                <!-- Overlay con CTAs en hover -->
                <div
                    class="absolute inset-0 flex items-end justify-center bg-gradient-to-t from-emerald-950/78 via-emerald-950/18 to-transparent p-3 opacity-0 transition-all duration-300 group-hover:opacity-100"
                >
                    <div
                        class="flex w-full translate-y-3 gap-2 transition-transform duration-300 group-hover:translate-y-0"
                    >
                        <Button
                            type="button"
                            size="sm"
                            variant="secondary"
                            class="flex-1 rounded-full bg-white/90 text-foreground shadow-md backdrop-blur hover:bg-white"
                            @click="openQuick"
                        >
                            <Eye class="size-4" />
                            <span class="hidden lg:inline">Vista</span>
                        </Button>
                        <Button
                            v-if="!outOfStock"
                            type="button"
                            size="sm"
                            class="gradient-brand flex-1 rounded-full text-white shadow-md hover:opacity-90"
                            @click="onAdd"
                        >
                            <ShoppingCart class="size-4" />
                            <span class="hidden lg:inline">Agregar</span>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Contenido -->
            <div class="flex flex-1 flex-col gap-1.5 p-4">
                <p
                    v-if="product.category"
                    class="text-[11px] font-extrabold tracking-wider text-brand uppercase"
                >
                    {{ product.category.name }}
                </p>
                <h3
                    class="line-clamp-2 font-display text-base leading-snug font-bold text-foreground"
                >
                    {{ product.name }}
                </h3>
                <p v-if="product.brand" class="text-sm text-muted-foreground">
                    {{ product.brand.name }}
                </p>

                <div
                    v-if="store.show_prices"
                    class="mt-auto flex items-end justify-between gap-2 pt-3"
                >
                    <div>
                        <span
                            class="font-display text-xl font-extrabold text-foreground"
                        >
                            {{
                                formatPrice(
                                    product.sale_price ?? product.price,
                                    store.currency_symbol,
                                )
                            }}
                        </span>
                        <span
                            v-if="showSale"
                            class="ml-1 text-xs font-medium text-muted-foreground line-through"
                        >
                            {{
                                formatPrice(
                                    product.price,
                                    store.currency_symbol,
                                )
                            }}
                        </span>
                    </div>
                    <span
                        class="grid size-9 place-items-center rounded-full bg-brand/10 text-brand opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                    >
                        <Heart class="size-4" />
                    </span>
                </div>
            </div>

            <!-- Decoración línea inferior animada -->
            <div
                class="gradient-brand absolute bottom-0 left-1/2 h-[3px] w-0 -translate-x-1/2 transition-all duration-500 group-hover:w-full"
            ></div>
        </Link>

        <QuickViewModal
            v-if="quickOpen"
            v-model:open="quickOpen"
            :product="product"
        />
    </div>
</template>

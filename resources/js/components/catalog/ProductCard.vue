<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Eye, Heart, ImageOff, ShoppingCart } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useCart } from '@/composables/useCart';
import { useWishlist } from '@/composables/useWishlist';
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

// Color del indicador de stock: verde disponible, ámbar pocas unidades, rojo agotado
const stockDotClass = computed(() => {
    if (outOfStock.value) {
        return 'bg-destructive';
    }

    if (lowStock.value) {
        return 'bg-amber-500';
    }

    return 'bg-emerald-500';
});

const isNew = computed(() => {
    if (!props.product.created_at) {
        return false;
    }

    const created = new Date(props.product.created_at).getTime();

    return Date.now() - created < 30 * 24 * 60 * 60 * 1000;
});

const imageFailed = ref(false);

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

// El estado del corazón depende de localStorage: renderizarlo solo tras el
// montaje para no romper la hidratación SSR.
const mounted = ref(false);
onMounted(() => (mounted.value = true));

const { has: inWishlist, toggle: toggleWishlist } = useWishlist();
const isFavorite = computed(
    () => mounted.value && inWishlist(props.product.id),
);
const onToggleFavorite = (e: MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    toggleWishlist({
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
            class="flex h-full flex-col overflow-hidden rounded-2xl bg-card ring-1 elev-1 ring-border/70 transition-all duration-200 ease-out hover:-translate-y-0.5 hover:[box-shadow:var(--shadow-lift)] hover:ring-primary/30"
        >
            <!-- Imagen -->
            <div
                class="relative m-2 mb-0 aspect-square overflow-hidden rounded-xl bg-muted"
            >
                <img
                    v-if="image && !imageFailed"
                    :src="image"
                    :alt="product.name"
                    class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.04]"
                    loading="lazy"
                    @error="imageFailed = true"
                />
                <div
                    v-else
                    class="flex h-full w-full flex-col items-center justify-center gap-2 text-muted-foreground/60"
                >
                    <ImageOff class="size-8" />
                    <span class="text-[11px] font-medium">Sin imagen</span>
                </div>

                <!-- Favorito -->
                <button
                    type="button"
                    class="absolute top-2 right-2 z-10 grid size-8 place-items-center rounded-full bg-card/90 shadow-sm ring-1 ring-black/5 transition hover:scale-105 hover:bg-card"
                    :aria-label="
                        isFavorite
                            ? 'Quitar de favoritos'
                            : 'Agregar a favoritos'
                    "
                    :aria-pressed="isFavorite"
                    @click="onToggleFavorite"
                >
                    <Heart
                        class="size-4 transition-colors"
                        :class="
                            isFavorite
                                ? 'fill-destructive text-destructive'
                                : 'text-muted-foreground'
                        "
                    />
                </button>

                <!-- Badges -->
                <div
                    class="absolute top-2 left-2 flex flex-col items-start gap-1"
                >
                    <span
                        v-if="showSale"
                        class="rounded-full bg-destructive px-2 py-0.5 text-[11px] font-bold text-destructive-foreground"
                    >
                        −{{ discountPct }}%
                    </span>
                    <span
                        v-else-if="isNew"
                        class="rounded-full bg-card/95 px-2 py-0.5 text-[11px] font-semibold text-primary ring-1 ring-primary/15"
                    >
                        Nuevo
                    </span>
                </div>

                <!-- Agotado -->
                <div
                    v-if="outOfStock"
                    class="absolute inset-x-0 bottom-0 bg-foreground/70 py-1.5 text-center text-xs font-semibold text-white"
                >
                    Agotado
                </div>

                <!-- Vista rápida en hover (escritorio) -->
                <div
                    v-else
                    class="absolute inset-x-2 bottom-2 hidden translate-y-1 opacity-0 transition duration-200 group-hover:translate-y-0 group-hover:opacity-100 lg:flex"
                >
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        class="h-8 flex-1 rounded-full bg-card/95 text-foreground shadow-sm hover:bg-card"
                        @click="openQuick"
                    >
                        <Eye class="size-4" />
                        Vista rápida
                    </Button>
                </div>
            </div>

            <!-- Contenido -->
            <div
                class="flex flex-1 flex-col gap-1 px-2.5 pt-2.5 pb-3 sm:px-3.5 sm:pt-3 sm:pb-3.5"
            >
                <p
                    v-if="product.category || product.brand"
                    class="truncate text-[11px] font-medium text-muted-foreground"
                >
                    <span v-if="product.category" class="text-primary">{{
                        product.category.name
                    }}</span>
                    <span v-if="product.category && product.brand"> · </span>
                    <span v-if="product.brand">{{ product.brand.name }}</span>
                </p>
                <h3
                    class="line-clamp-2 min-h-[2.5rem] text-sm leading-5 font-semibold text-foreground"
                >
                    {{ product.name }}
                </h3>

                <div
                    class="mt-auto flex items-end justify-between gap-1.5 pt-2"
                >
                    <div class="min-w-0">
                        <template v-if="store.show_prices">
                            <p
                                v-if="showSale"
                                class="tabular truncate text-xs text-muted-foreground line-through"
                            >
                                {{
                                    formatPrice(
                                        product.price,
                                        store.currency_symbol,
                                    )
                                }}
                            </p>
                            <p
                                class="tabular font-display text-[15px] leading-tight font-extrabold tracking-tight whitespace-nowrap sm:text-lg"
                                :class="
                                    showSale
                                        ? 'text-destructive'
                                        : 'text-foreground'
                                "
                            >
                                {{
                                    formatPrice(
                                        product.sale_price ?? product.price,
                                        store.currency_symbol,
                                    )
                                }}
                            </p>
                        </template>
                        <p
                            v-if="store.show_stock"
                            class="mt-1 flex items-center gap-1.5 text-[11px] text-muted-foreground"
                        >
                            <span
                                class="size-1.5 rounded-full"
                                :class="stockDotClass"
                            ></span>
                            <span v-if="outOfStock">Sin stock</span>
                            <span v-else-if="lowStock"
                                >Últimas {{ product.stock }} unidades</span
                            >
                            <span v-else>{{ product.stock }} disponibles</span>
                        </p>
                    </div>

                    <button
                        v-if="!outOfStock"
                        type="button"
                        class="grid size-8 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground transition hover:scale-105 hover:bg-primary/90 active:scale-95 sm:size-9"
                        aria-label="Agregar al carrito"
                        @click="onAdd"
                    >
                        <ShoppingCart class="size-4" />
                    </button>
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

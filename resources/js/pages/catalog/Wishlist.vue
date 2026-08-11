<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Heart, ImageOff, ShoppingCart, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useCart } from '@/composables/useCart';
import { useWishlist } from '@/composables/useWishlist';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({ layout: PublicLayout });

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);

// Los favoritos viven en localStorage: renderizar solo tras el montaje
// para no romper la hidratación SSR.
const mounted = ref(false);
onMounted(() => (mounted.value = true));

const { items, remove, clear } = useWishlist();
const { add: addToCart } = useCart();

const moveToCart = (productId: number) => {
    const item = items.value.find((i) => i.product_id === productId);

    if (!item) {
        return;
    }

    addToCart({
        product_id: item.product_id,
        slug: item.slug,
        name: item.name,
        code: item.code,
        price: item.price,
        image: item.image,
    });
    remove(productId);
};
</script>

<template>
    <Head title="Favoritos" />

    <div class="mx-auto w-full max-w-[1400px] px-6 py-8 lg:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Tus favoritos</h1>
            <Button
                v-if="mounted && items.length"
                variant="ghost"
                size="sm"
                class="text-muted-foreground"
                @click="clear"
            >
                <Trash2 class="size-4" />
                Vaciar lista
            </Button>
        </div>

        <div
            v-if="mounted && items.length"
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5"
        >
            <div
                v-for="item in items"
                :key="item.product_id"
                class="group flex flex-col overflow-hidden rounded-lg border border-border bg-card"
            >
                <Link
                    :href="`/catalogo/${item.slug}`"
                    class="relative aspect-square overflow-hidden bg-muted"
                >
                    <img
                        v-if="item.image"
                        :src="item.image"
                        :alt="item.name"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                        loading="lazy"
                    />
                    <div
                        v-else
                        class="flex h-full w-full items-center justify-center text-muted-foreground"
                    >
                        <ImageOff class="size-8 opacity-40" />
                    </div>
                </Link>

                <div class="flex flex-1 flex-col gap-1 p-3">
                    <Link
                        :href="`/catalogo/${item.slug}`"
                        class="line-clamp-2 text-sm leading-snug font-medium hover:underline"
                    >
                        {{ item.name }}
                    </Link>
                    <p
                        v-if="store.show_prices"
                        class="text-base font-semibold"
                    >
                        {{ formatPrice(item.price, store.currency_symbol) }}
                    </p>

                    <div class="mt-auto flex gap-2 pt-2">
                        <Button
                            type="button"
                            size="sm"
                            class="h-8 flex-1"
                            @click="moveToCart(item.product_id)"
                        >
                            <ShoppingCart class="size-4" />
                            <span class="hidden lg:inline">Al carrito</span>
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-8"
                            aria-label="Quitar de favoritos"
                            @click="remove(item.product_id)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="rounded-lg border bg-card p-12 text-center"
        >
            <Heart class="mx-auto mb-3 size-12 text-muted-foreground/40" />
            <p class="mb-4 text-muted-foreground">
                Aún no tienes productos favoritos. Marca el corazón de un
                producto para guardarlo aquí.
            </p>
            <Button as-child>
                <Link href="/catalogo">Explorar el catálogo</Link>
            </Button>
        </div>
    </div>
</template>

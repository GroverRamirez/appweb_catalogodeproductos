<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Flame, Folder, ImageOff, Loader2, Search, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatPrice, productMainImage } from '@/lib/catalog';
import type { CatalogProduct, StoreSettings } from '@/lib/catalog';
import { suggestions } from '@/routes/catalog';

withDefaults(
    defineProps<{
        placeholder?: string;
        compact?: boolean;
    }>(),
    {
        placeholder: 'Buscar productos...',
        compact: false,
    },
);

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);

const q = ref(
    new URLSearchParams(
        typeof window !== 'undefined' ? window.location.search : '',
    ).get('q') ?? '',
);

const searchContainer = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const isOpen = ref(false);
const loading = ref(false);
const products = ref<CatalogProduct[]>([]);
const categories = ref<Array<{ id: number; name: string; slug: string }>>([]);
const totalMatches = ref(0);
const selectedIndex = ref(-1);

let searchTimer: ReturnType<typeof setTimeout> | null = null;
let abortController: AbortController | null = null;

const onInput = () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    const trimmed = q.value.trim();

    if (trimmed.length < 2) {
        products.value = [];
        categories.value = [];
        totalMatches.value = 0;
        loading.value = false;
        isOpen.value = false;
        selectedIndex.value = -1;

        return;
    }

    loading.value = true;
    isOpen.value = true;
    selectedIndex.value = -1;

    searchTimer = setTimeout(async () => {
        if (abortController) {
            abortController.abort();
        }

        abortController = new AbortController();

        try {
            const url = suggestions.url({ query: { q: trimmed } });
            const res = await fetch(url, {
                headers: { Accept: 'application/json' },
                signal: abortController.signal,
            });

            if (!res.ok) {
                loading.value = false;

                return;
            }

            const data = await res.json();
            products.value = data.products ?? [];
            categories.value = data.categories ?? [];
            totalMatches.value = data.total ?? 0;
            isOpen.value = true;
        } catch (e: any) {
            if (e.name !== 'AbortError') {
                console.error('Error fetching search suggestions', e);
            }
        } finally {
            loading.value = false;
        }
    }, 250);
};

const clearQuery = () => {
    q.value = '';
    products.value = [];
    categories.value = [];
    totalMatches.value = 0;
    isOpen.value = false;
    selectedIndex.value = -1;
    inputRef.value?.focus();
};

const submitSearch = () => {
    const trimmed = q.value.trim();

    if (!trimmed) {
        return;
    }

    // Si había un elemento seleccionado con las flechas
    if (
        selectedIndex.value >= 0 &&
        selectedIndex.value < products.value.length
    ) {
        selectProduct(products.value[selectedIndex.value]);

        return;
    }

    isOpen.value = false;
    router.get('/catalogo', { q: trimmed }, { preserveState: false });
};

const selectProduct = (product: CatalogProduct) => {
    isOpen.value = false;
    router.get(`/catalogo/${product.slug}`);
};

const selectCategory = (categorySlug: string) => {
    isOpen.value = false;
    router.get(
        '/catalogo',
        { category: categorySlug },
        { preserveState: false },
    );
};

const onKeydown = (e: KeyboardEvent) => {
    if (!isOpen.value) {
        if (e.key === 'ArrowDown' && q.value.trim().length >= 2) {
            isOpen.value = true;
            onInput();
        }

        return;
    }

    if (e.key === 'Escape') {
        isOpen.value = false;
        selectedIndex.value = -1;

        return;
    }

    const max = products.value.length;

    if (max === 0) {
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex.value = (selectedIndex.value + 1) % max;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex.value =
            selectedIndex.value <= 0 ? max - 1 : selectedIndex.value - 1;
    }
};

const handleClickOutside = (e: MouseEvent) => {
    if (
        searchContainer.value &&
        !searchContainer.value.contains(e.target as Node)
    ) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('pointerdown', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleClickOutside);

    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    if (abortController) {
        abortController.abort();
    }
});
</script>

<template>
    <div ref="searchContainer" class="relative w-full" @keydown="onKeydown">
        <form
            @submit.prevent="submitSearch"
            class="relative w-full"
            role="search"
        >
            <Search
                class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground sm:left-4"
            />

            <input
                ref="inputRef"
                v-model="q"
                type="text"
                :placeholder="placeholder"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                @input="onInput"
                @focus="q.trim().length >= 2 && (isOpen = true)"
                class="w-full rounded-full border-transparent bg-muted text-sm shadow-none transition-colors hover:bg-secondary focus:border-ring focus:bg-card focus:ring-2 focus:ring-ring/20 focus:outline-none"
                :class="[
                    compact ? 'h-10 pr-9 pl-9' : 'h-11 pr-24 pl-11 md:pr-28',
                ]"
            />

            <!-- Indicador de carga o botón de limpiar -->
            <div
                class="absolute top-1/2 flex -translate-y-1/2 items-center gap-1"
                :class="compact ? 'right-2' : 'right-2 md:right-2.5'"
            >
                <Loader2
                    v-if="loading"
                    class="size-4 animate-spin text-muted-foreground"
                />
                <button
                    v-else-if="q.length > 0"
                    type="button"
                    class="grid size-6 place-items-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                    aria-label="Limpiar búsqueda"
                    @click="clearQuery"
                >
                    <X class="size-3.5" />
                </button>

                <Button
                    v-if="!compact"
                    type="submit"
                    size="sm"
                    class="hidden h-8 rounded-full px-4 shadow-none md:inline-flex"
                >
                    Buscar
                </Button>
            </div>
        </form>

        <!-- Dropdown de resultados en vivo -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="translate-y-1 opacity-0 scale-[0.98]"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-1 opacity-0 scale-[0.98]"
        >
            <div
                v-if="isOpen && q.trim().length >= 2"
                class="absolute top-full right-0 left-0 z-50 mt-2 max-h-[80vh] overflow-y-auto rounded-2xl border border-border/80 bg-card p-2 shadow-2xl ring-1 ring-black/5"
            >
                <!-- Categorías sugeridas -->
                <div
                    v-if="categories.length > 0"
                    class="mb-2 border-b border-border/60 px-2 pb-2"
                >
                    <p
                        class="mb-1.5 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Categorías
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            type="button"
                            @click="selectCategory(cat.slug)"
                            class="inline-flex items-center gap-1 rounded-full bg-secondary px-2.5 py-1 text-xs font-medium text-foreground transition hover:bg-primary hover:text-primary-foreground"
                        >
                            <Folder class="size-3 text-muted-foreground" />
                            {{ cat.name }}
                        </button>
                    </div>
                </div>

                <!-- Lista de productos -->
                <div v-if="products.length > 0" class="space-y-1">
                    <p
                        class="px-2 pt-1 pb-1 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Productos sugeridos ({{ totalMatches }})
                    </p>

                    <div
                        v-for="(p, index) in products"
                        :key="p.id"
                        role="button"
                        tabindex="0"
                        @click="selectProduct(p)"
                        class="flex cursor-pointer items-center gap-3 rounded-xl p-2 transition-colors"
                        :class="[
                            selectedIndex === index
                                ? 'bg-muted text-primary'
                                : 'text-foreground hover:bg-muted/70',
                        ]"
                    >
                        <!-- Miniatura -->
                        <div
                            class="relative size-12 shrink-0 overflow-hidden rounded-lg border border-border/50 bg-muted"
                        >
                            <img
                                v-if="productMainImage(p)"
                                :src="productMainImage(p)!"
                                :alt="p.name"
                                class="h-full w-full object-cover"
                            />
                            <div
                                v-else
                                class="grid h-full w-full place-items-center text-muted-foreground/50"
                            >
                                <ImageOff class="size-4" />
                            </div>
                        </div>

                        <!-- Detalles -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-semibold text-muted-foreground"
                                >
                                    {{ p.code }}
                                </span>
                                <span
                                    v-if="p.category"
                                    class="truncate text-[11px] text-muted-foreground"
                                >
                                    {{ p.category.name }}
                                </span>
                            </div>
                            <p
                                class="truncate text-sm leading-snug font-semibold"
                            >
                                {{ p.name }}
                            </p>
                            <div class="mt-0.5 flex items-center gap-2">
                                <span
                                    v-if="store.show_prices"
                                    class="tabular text-xs font-extrabold text-foreground"
                                    :class="
                                        p.sale_price ? 'text-destructive' : ''
                                    "
                                >
                                    {{
                                        formatPrice(
                                            p.sale_price ?? p.price,
                                            store.currency_symbol,
                                        )
                                    }}
                                </span>
                                <span
                                    v-if="store.show_prices && p.sale_price"
                                    class="tabular text-[11px] text-muted-foreground line-through"
                                >
                                    {{
                                        formatPrice(
                                            p.price,
                                            store.currency_symbol,
                                        )
                                    }}
                                </span>

                                <!-- Badge de stock -->
                                <span
                                    v-if="store.show_stock && p.stock <= 0"
                                    class="py-0.2 rounded-full bg-destructive/10 px-1.5 text-[10px] font-medium text-destructive"
                                >
                                    Sin stock
                                </span>
                                <span
                                    v-else-if="store.show_stock && p.stock <= 5"
                                    class="py-0.2 inline-flex items-center gap-0.5 rounded-full bg-amber-500/15 px-1.5 text-[10px] font-bold text-amber-600 dark:text-amber-400"
                                >
                                    <Flame class="size-2.5 fill-current" />
                                    ¡Últimas {{ p.stock }}!
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer: Ver todos -->
                    <div
                        class="border-t border-border/60 pt-2 pb-1 text-center"
                    >
                        <button
                            type="button"
                            @click="submitSearch"
                            class="w-full rounded-xl py-1.5 text-xs font-semibold text-primary transition hover:bg-primary/10"
                        >
                            Ver todos los {{ totalMatches }} resultados para "{{
                                q
                            }}" →
                        </button>
                    </div>
                </div>

                <!-- Estado vacío -->
                <div v-else-if="!loading" class="px-4 py-6 text-center">
                    <Search
                        class="mx-auto mb-2 size-7 text-muted-foreground/50"
                    />
                    <p class="text-sm font-semibold text-foreground">
                        No se encontraron productos
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        No hay coincidencias para "{{ q }}". Prueba con otro
                        término o código.
                    </p>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Minus,
    Plus,
    ShoppingCart,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCart } from '@/composables/useCart';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

const {
    items,
    count,
    subtotal,
    drawerOpen,
    setQuantity,
    remove,
    clear,
    validate,
    removedByValidation,
} = useCart();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);

// Validar al abrir el drawer (respeta el cooldown de 5 min del composable)
watch(drawerOpen, (open) => {
    if (open && items.value.length) {
        validate();
    }
});
</script>

<template>
    <Sheet :open="drawerOpen" @update:open="(v) => (drawerOpen = v)">
        <SheetContent class="flex w-full flex-col p-0 sm:max-w-md">
            <SheetHeader class="border-b p-4">
                <SheetTitle class="flex items-center gap-2">
                    <ShoppingCart class="size-5" /> Tu carrito
                    <span class="text-sm text-muted-foreground">
                        ({{ count }} item<span v-if="count !== 1">s</span>)
                    </span>
                </SheetTitle>
                <SheetDescription class="sr-only">
                    Productos agregados a tu carrito de consulta
                </SheetDescription>
            </SheetHeader>

            <!-- Aviso de productos removidos por validación -->
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
            >
                <div
                    v-if="removedByValidation.length > 0"
                    class="flex items-start gap-2 border-b bg-amber-50 px-4 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    <AlertTriangle
                        class="mt-0.5 size-3.5 shrink-0 text-amber-500"
                    />
                    <p>
                        <strong
                            >{{
                                removedByValidation.length
                            }}
                            producto(s)</strong
                        >
                        eliminados del carrito por no estar disponibles.
                    </p>
                </div>
            </Transition>

            <div class="flex-1 overflow-y-auto p-4">
                <div
                    v-if="!items.length"
                    class="flex h-full flex-col items-center justify-center gap-2 text-center text-muted-foreground"
                >
                    <ShoppingCart class="size-12 opacity-30" />
                    <p>Tu carrito está vacío</p>
                </div>

                <ul v-else class="space-y-3">
                    <li
                        v-for="item in items"
                        :key="item.product_id"
                        class="flex gap-3 rounded-md border p-2"
                    >
                        <Link
                            :href="`/catalogo/${item.slug}`"
                            class="shrink-0"
                            @click="drawerOpen = false"
                        >
                            <img
                                v-if="item.image"
                                :src="item.image"
                                :alt="item.name"
                                class="size-16 rounded object-cover"
                            />
                            <div v-else class="size-16 rounded bg-muted"></div>
                        </Link>
                        <div class="flex flex-1 flex-col">
                            <Link
                                :href="`/catalogo/${item.slug}`"
                                class="line-clamp-2 text-sm font-medium hover:underline"
                                @click="drawerOpen = false"
                            >
                                {{ item.name }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ item.code }}
                            </p>
                            <div
                                class="mt-auto flex items-center justify-between gap-2"
                            >
                                <div
                                    class="inline-flex items-center rounded-md border"
                                >
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        @click="
                                            setQuantity(
                                                item.product_id,
                                                item.quantity - 1,
                                            )
                                        "
                                    >
                                        <Minus class="size-3" />
                                    </Button>
                                    <span class="min-w-8 text-center text-sm">
                                        {{ item.quantity }}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        @click="
                                            setQuantity(
                                                item.product_id,
                                                item.quantity + 1,
                                            )
                                        "
                                    >
                                        <Plus class="size-3" />
                                    </Button>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold">
                                        {{
                                            formatPrice(
                                                item.price * item.quantity,
                                                store.currency_symbol,
                                            )
                                        }}
                                    </p>
                                    <button
                                        type="button"
                                        class="text-xs text-destructive hover:underline"
                                        @click="remove(item.product_id)"
                                    >
                                        <Trash2 class="inline size-3" /> Quitar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <div v-if="items.length" class="space-y-3 border-t bg-muted/30 p-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-muted-foreground">Subtotal</span>
                    <span class="text-xl font-semibold">
                        {{ formatPrice(subtotal, store.currency_symbol) }}
                    </span>
                </div>
                <Button as-child class="w-full" size="lg">
                    <Link href="/carrito" @click="drawerOpen = false">
                        Continuar al envío
                    </Link>
                </Button>
                <button
                    type="button"
                    class="w-full text-xs text-muted-foreground hover:underline"
                    @click="clear"
                >
                    <X class="inline size-3" /> Vaciar carrito
                </button>
            </div>
        </SheetContent>
    </Sheet>
</template>

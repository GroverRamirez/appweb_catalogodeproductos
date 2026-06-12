<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Phone } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    buildWhatsAppLink,
    formatPrice,
    productMainImage,
} from '@/lib/catalog';
import type { CatalogProduct, StoreSettings } from '@/lib/catalog';

const props = defineProps<{
    open: boolean;
    product: CatalogProduct;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);

const image = computed(() => productMainImage(props.product));

const showSale = computed(
    () =>
        !!props.product.sale_price &&
        Number(props.product.sale_price) < Number(props.product.price),
);

const whatsappHref = computed(() => {
    if (!store.value.whatsapp) {
        return null;
    }

    return buildWhatsAppLink(
        store.value.whatsapp,
        store.value.whatsapp_template,
        { name: props.product.name, code: props.product.code },
    );
});

const consultViaWhatsApp = () => {
    if (!whatsappHref.value) {
        return;
    }

    // Registrar la consulta y luego abrir WhatsApp
    router.post(
        '/consultas',
        {
            customer_name: 'Consulta rápida',
            customer_phone: store.value.whatsapp,
            source: 'whatsapp',
            product_id: props.product.id,
            quantity: 1,
            message: `Vista rápida del producto ${props.product.code}`,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                window.open(whatsappHref.value!, '_blank');
            },
        },
    );
};
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="max-w-3xl p-0">
            <div class="grid gap-0 md:grid-cols-2">
                <div class="aspect-square overflow-hidden bg-muted">
                    <img
                        v-if="image"
                        :src="image"
                        :alt="product.name"
                        class="h-full w-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-full w-full items-center justify-center text-muted-foreground"
                    >
                        Sin imagen
                    </div>
                </div>
                <div class="flex flex-col gap-3 p-6">
                    <DialogHeader class="space-y-1">
                        <p
                            v-if="product.category"
                            class="text-xs text-muted-foreground uppercase"
                        >
                            {{ product.category.name }}
                        </p>
                        <DialogTitle>{{ product.name }}</DialogTitle>
                        <DialogDescription class="sr-only">
                            Vista rápida del producto {{ product.name }}
                        </DialogDescription>
                        <p
                            v-if="product.brand"
                            class="text-xs text-muted-foreground"
                        >
                            {{ product.brand.name }} · {{ product.code }}
                        </p>
                    </DialogHeader>

                    <p
                        v-if="product.short_description"
                        class="text-sm text-muted-foreground"
                    >
                        {{ product.short_description }}
                    </p>

                    <div
                        v-if="store.show_prices"
                        class="flex items-baseline gap-2"
                    >
                        <span class="text-2xl font-semibold">
                            {{
                                formatPrice(
                                    product.sale_price ?? product.price,
                                    store.currency_symbol,
                                )
                            }}
                        </span>
                        <span
                            v-if="showSale"
                            class="text-sm text-muted-foreground line-through"
                        >
                            {{
                                formatPrice(
                                    product.price,
                                    store.currency_symbol,
                                )
                            }}
                        </span>
                    </div>

                    <Badge
                        v-if="store.show_stock"
                        :variant="product.stock > 0 ? 'default' : 'destructive'"
                        class="w-fit"
                    >
                        {{
                            product.stock > 0
                                ? `${product.stock} disponibles`
                                : 'Sin stock'
                        }}
                    </Badge>

                    <div class="mt-auto flex flex-col gap-2 pt-4">
                        <Button
                            v-if="whatsappHref"
                            class="w-full bg-emerald-600 text-white hover:bg-emerald-700"
                            @click="consultViaWhatsApp"
                        >
                            <Phone class="size-4" /> Consultar por WhatsApp
                        </Button>
                        <Button variant="outline" as-child>
                            <Link :href="`/catalogo/${product.slug}`">
                                Ver detalle completo
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

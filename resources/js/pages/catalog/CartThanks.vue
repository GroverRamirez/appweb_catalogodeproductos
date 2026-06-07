<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { formatPrice  } from '@/lib/catalog';
import type {StoreSettings} from '@/lib/catalog';

defineOptions({ layout: PublicLayout });

type Item = {
    id: number;
    product_name_snapshot: string;
    product_code_snapshot: string | null;
    quantity: number;
    unit_price: string;
};

type Inquiry = {
    id: number;
    customer_name: string;
    customer_phone: string;
    total_estimated: string | null;
    items: Item[];
};

defineProps<{ inquiry: Inquiry | null }>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
</script>

<template>
    <Head title="¡Gracias por tu pedido!" />

    <div class="mx-auto max-w-2xl px-4 py-12">
        <Card>
            <CardHeader class="items-center text-center">
                <CheckCircle2 class="mb-2 size-12 text-emerald-500" />
                <CardTitle class="text-2xl">¡Pedido recibido!</CardTitle>
                <p class="text-sm text-muted-foreground">
                    Tu solicitud #{{ inquiry?.id ?? '...' }} fue registrada. Te contactaremos pronto.
                </p>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-if="inquiry" class="rounded-md border bg-muted/40 p-4">
                    <p class="mb-2 text-sm font-medium">Resumen</p>
                    <ul class="space-y-1 text-sm">
                        <li v-for="i in inquiry.items" :key="i.id" class="flex justify-between">
                            <span>{{ i.quantity }}x {{ i.product_name_snapshot }}</span>
                            <span class="font-medium">
                                {{
                                    formatPrice(
                                        Number(i.unit_price) * i.quantity,
                                        store.currency_symbol,
                                    )
                                }}
                            </span>
                        </li>
                    </ul>
                    <div
                        v-if="inquiry.total_estimated"
                        class="mt-3 flex justify-between border-t pt-2 text-sm font-semibold"
                    >
                        <span>Total estimado</span>
                        <span>
                            {{ formatPrice(inquiry.total_estimated, store.currency_symbol) }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap justify-center gap-2">
                    <Button as-child>
                        <Link href="/catalogo">Seguir explorando</Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/">Volver al inicio</Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Ban } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/composables/usePermissions';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Compras', href: '/admin/purchases' },
            { title: 'Detalle', href: '' },
        ],
    }),
});

type Item = {
    id: number;
    product_id: number | null;
    product_name_snapshot: string;
    product_code_snapshot: string | null;
    quantity: number;
    unit_cost: string;
    subtotal: number;
};

type PurchaseDetail = {
    id: number;
    reference_number: string | null;
    status: string;
    total_cost: string;
    notes: string | null;
    created_at: string;
    supplier: { id: number; name: string } | null;
    items: Item[];
    creator: { id: number; name: string } | null;
    voider: { id: number; name: string } | null;
    voided_at: string | null;
};

const props = defineProps<{ purchase: PurchaseDetail }>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: string | number) => formatPrice(n, store.value.currency_symbol);
const { can } = usePermissions();

const voidPurchase = () => {
    if (
        !confirm(
            '¿Anular esta compra? El stock que sumó se va a descontar de cada producto.',
        )
    ) {
        return;
    }

    router.patch(`/admin/purchases/${props.purchase.id}/void`);
};
</script>

<template>
    <Head :title="`Compra ${purchase.reference_number ?? `#${purchase.id}`}`" />

    <div class="mx-auto max-w-4xl space-y-4 p-3 md:p-4">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="icon-sm" as-child>
                    <Link href="/admin/purchases"
                        ><ArrowLeft class="size-4"
                    /></Link>
                </Button>
                <h1 class="text-xl font-semibold">
                    {{ purchase.reference_number ?? `Compra #${purchase.id}` }}
                </h1>
                <Badge
                    :variant="
                        purchase.status === 'confirmada'
                            ? 'success'
                            : 'secondary'
                    "
                >
                    {{
                        purchase.status === 'confirmada'
                            ? 'Confirmada'
                            : 'Anulada'
                    }}
                </Badge>
            </div>
            <Button
                v-if="purchase.status === 'confirmada' && can('inventory.adjust')"
                variant="outline"
                class="text-destructive hover:text-destructive"
                @click="voidPurchase"
            >
                <Ban class="size-4" /> Anular compra
            </Button>
        </div>

        <Card class="gap-3 py-4">
            <CardContent class="grid gap-3 sm:grid-cols-3">
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Proveedor
                    </p>
                    <p class="font-medium">
                        {{ purchase.supplier?.name ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Fecha
                    </p>
                    <p class="font-medium">
                        {{ new Date(purchase.created_at).toLocaleString() }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Registrada por
                    </p>
                    <p class="font-medium">
                        {{ purchase.creator?.name ?? '—' }}
                    </p>
                </div>
                <div v-if="purchase.notes" class="sm:col-span-3">
                    <p class="text-xs text-muted-foreground uppercase">
                        Notas
                    </p>
                    <p>{{ purchase.notes }}</p>
                </div>
                <div v-if="purchase.status === 'anulada'" class="sm:col-span-3">
                    <p class="text-xs text-muted-foreground uppercase">
                        Anulada
                    </p>
                    <p>
                        {{ purchase.voider?.name ?? '—' }} ·
                        {{
                            purchase.voided_at
                                ? new Date(purchase.voided_at).toLocaleString()
                                : ''
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card class="gap-3 overflow-hidden py-4">
            <CardHeader class="pb-0"
                ><CardTitle>Productos</CardTitle></CardHeader
            >
            <CardContent class="p-0 pt-3">
                <table class="w-full text-sm">
                    <thead
                        class="admin-table-header text-left text-[11px] tracking-wider uppercase"
                    >
                        <tr>
                            <th class="px-4 py-2">Producto</th>
                            <th class="px-4 py-2 text-right">Cantidad</th>
                            <th class="px-4 py-2 text-right">Costo unit.</th>
                            <th class="px-4 py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/80">
                        <tr v-for="item in purchase.items" :key="item.id">
                            <td class="px-4 py-3">
                                <Link
                                    v-if="item.product_id"
                                    :href="`/admin/products/${item.product_id}/edit`"
                                    class="font-medium hover:text-primary hover:underline"
                                >
                                    {{ item.product_name_snapshot }}
                                </Link>
                                <span v-else class="font-medium">
                                    {{ item.product_name_snapshot }}
                                </span>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.product_code_snapshot }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ item.quantity }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                {{ cur(item.unit_cost) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                {{ cur(item.subtotal) }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t">
                            <td
                                colspan="3"
                                class="px-4 py-3 text-right font-semibold"
                            >
                                Total
                            </td>
                            <td
                                class="px-4 py-3 text-right font-mono font-semibold"
                            >
                                {{ cur(purchase.total_cost) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </CardContent>
        </Card>
    </div>
</template>

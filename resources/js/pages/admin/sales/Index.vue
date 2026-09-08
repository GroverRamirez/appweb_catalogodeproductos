<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Plus, Search, ShoppingCart } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';
import { PAYMENT_METHOD_LABELS, receiptNumber } from '@/lib/sales';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Ventas', href: '/admin/sales' },
        ],
    }),
});

type SaleRow = {
    id: number;
    receipt_number: number;
    customer_name: string | null;
    customer_phone: string | null;
    payment_method: string;
    origin: string;
    status: string;
    total: string;
    items_count: number;
    created_at: string;
};

const props = defineProps<{
    sales: {
        data: SaleRow[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { q?: string; status?: string };
}>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: string) => formatPrice(n, store.value.currency_symbol);
const { can } = usePermissions();

const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');

let timer: ReturnType<typeof setTimeout> | null = null;
watch([q, status], () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        router.get(
            '/admin/sales',
            { q: q.value, status: status.value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head title="Ventas" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="ShoppingCart"
            eyebrow="Operación"
            title="Ventas"
            description="Ventas registradas, con el stock que descontaron y el recibo emitido."
        >
            <template #actions>
                <Button v-if="can('sales.create')" as-child class="rounded-md">
                    <Link href="/admin/sales/create">
                        <Plus class="size-4" /> Nueva venta
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-3">
            <div class="relative max-w-sm flex-1">
                <Search
                    class="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    placeholder="Buscar por recibo, cliente o teléfono..."
                    class="pl-8"
                />
            </div>
            <select
                v-model="status"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="">Todas</option>
                <option value="confirmada">Confirmadas</option>
                <option value="anulada">Anuladas</option>
            </select>
        </div>

        <div class="overflow-x-auto rounded-md border bg-card">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-3 py-2">Recibo</th>
                        <th class="px-3 py-2">Cliente</th>
                        <th class="px-3 py-2">Fecha</th>
                        <th class="px-3 py-2">Pago</th>
                        <th class="px-3 py-2 text-right">Productos</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="w-28 px-3 py-2">Estado</th>
                        <th class="w-16 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="s in sales.data"
                        :key="s.id"
                        class="hover:bg-muted/30"
                    >
                        <td class="px-3 py-2 font-mono font-medium">
                            {{ receiptNumber(s.receipt_number) }}
                        </td>
                        <td class="px-3 py-2">
                            <span
                                :class="
                                    s.customer_name
                                        ? ''
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ s.customer_name ?? 'Consumidor final' }}
                            </span>
                            <span
                                v-if="s.origin === 'consulta'"
                                class="ml-2 text-xs text-muted-foreground"
                            >
                                (de consulta)
                            </span>
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{ new Date(s.created_at).toLocaleDateString() }}
                        </td>
                        <td class="px-3 py-2 text-muted-foreground">
                            {{
                                PAYMENT_METHOD_LABELS[s.payment_method] ??
                                s.payment_method
                            }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            {{ s.items_count }}
                        </td>
                        <td class="px-3 py-2 text-right font-mono">
                            {{ cur(s.total) }}
                        </td>
                        <td class="px-3 py-2">
                            <Badge
                                :variant="
                                    s.status === 'confirmada'
                                        ? 'success'
                                        : 'secondary'
                                "
                            >
                                {{
                                    s.status === 'confirmada'
                                        ? 'Confirmada'
                                        : 'Anulada'
                                }}
                            </Badge>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <Button variant="ghost" size="icon-sm" as-child>
                                <Link :href="`/admin/sales/${s.id}`">
                                    <Eye class="size-4" />
                                </Link>
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!sales.data.length">
                        <td
                            colspan="8"
                            class="px-3 py-6 text-center text-muted-foreground"
                        >
                            Sin resultados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :links="sales.links"
            :from="sales.from"
            :to="sales.to"
            :total="sales.total"
        />
    </div>
</template>

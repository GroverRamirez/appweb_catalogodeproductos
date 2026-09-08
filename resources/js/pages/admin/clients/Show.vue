<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    MessageSquare,
    Pencil,
    Receipt,
    Trash2,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import StatCard from '@/components/admin/StatCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/composables/usePermissions';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';
import { PAYMENT_METHOD_LABELS, receiptNumber } from '@/lib/sales';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Clientes', href: '/admin/clients' },
            { title: 'Ficha', href: '' },
        ],
    }),
});

type Sale = {
    id: number;
    receipt_number: number;
    status: string;
    total: string;
    payment_method: string;
    created_at: string;
};

type Inquiry = {
    id: number;
    status: string;
    total_estimated: string | null;
    created_at: string;
};

type Client = {
    id: number;
    name: string;
    phone: string | null;
    id_card: string | null;
    email: string | null;
    address: string | null;
    notes: string | null;
    is_active: boolean;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
    sales: Sale[];
    inquiries: Inquiry[];
};

const props = defineProps<{
    client: Client;
    summary: {
        purchases: number;
        total_spent: number;
        last_purchase: string | null;
    };
}>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: string | number | null) =>
    formatPrice(n ?? 0, store.value.currency_symbol);
const { can } = usePermissions();

const fmtDate = (value: string | null) =>
    value ? new Date(value).toLocaleDateString() : '—';

const destroy = () => {
    if (
        !confirm(
            `¿Dar de baja a "${props.client.name}"? Su historial de compras se conserva.`,
        )
    ) {
        return;
    }

    router.delete(`/admin/clients/${props.client.id}`);
};
</script>

<template>
    <Head :title="client.name" />

    <div class="mx-auto max-w-4xl space-y-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <Button variant="ghost" size="icon-sm" as-child>
                    <Link href="/admin/clients">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <h1 class="text-xl font-semibold">{{ client.name }}</h1>
                <Badge :variant="client.is_active ? 'success' : 'secondary'">
                    {{ client.is_active ? 'Activo' : 'Inactivo' }}
                </Badge>
            </div>
            <div class="flex items-center gap-2">
                <Button v-if="can('clients.update')" variant="outline" as-child>
                    <Link :href="`/admin/clients/${client.id}/edit`">
                        <Pencil class="size-4" /> Editar
                    </Link>
                </Button>
                <Button
                    v-if="can('clients.delete')"
                    variant="outline"
                    class="text-destructive hover:text-destructive"
                    @click="destroy"
                >
                    <Trash2 class="size-4" /> Dar de baja
                </Button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <StatCard
                label="Compras"
                :value="summary.purchases"
                :icon="Receipt"
            />
            <StatCard
                label="Total gastado"
                :value="cur(summary.total_spent)"
                tone="success"
                :icon="Wallet"
            />
            <StatCard
                label="Última compra"
                :value="fmtDate(summary.last_purchase)"
                :icon="MessageSquare"
            />
        </div>

        <Card class="gap-3 py-4">
            <CardHeader class="pb-0">
                <CardTitle>Datos</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-3 sm:grid-cols-3">
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Teléfono
                    </p>
                    <p class="font-mono font-medium">
                        {{ client.phone ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">CI</p>
                    <p class="font-medium">{{ client.id_card ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">Email</p>
                    <p class="font-medium">{{ client.email ?? '—' }}</p>
                </div>
                <div v-if="client.address" class="sm:col-span-2">
                    <p class="text-xs text-muted-foreground uppercase">
                        Dirección
                    </p>
                    <p>{{ client.address }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground uppercase">
                        Cuenta web
                    </p>
                    <p class="font-medium">
                        {{ client.user ? client.user.email : 'Sin cuenta' }}
                    </p>
                </div>
                <div v-if="client.notes" class="sm:col-span-3">
                    <p class="text-xs text-muted-foreground uppercase">Notas</p>
                    <p class="whitespace-pre-line">{{ client.notes }}</p>
                </div>
            </CardContent>
        </Card>

        <Card class="gap-3 overflow-hidden py-4">
            <CardHeader class="pb-0">
                <CardTitle>Compras</CardTitle>
            </CardHeader>
            <CardContent class="p-0 pt-3">
                <table v-if="client.sales.length" class="w-full text-sm">
                    <thead
                        class="admin-table-header text-left text-[11px] tracking-wider uppercase"
                    >
                        <tr>
                            <th class="px-4 py-2">Recibo</th>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Pago</th>
                            <th class="px-4 py-2 text-right">Total</th>
                            <th class="w-28 px-4 py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/80">
                        <tr v-for="s in client.sales" :key="s.id">
                            <td class="px-4 py-2">
                                <Link
                                    :href="`/admin/sales/${s.id}`"
                                    class="font-mono font-medium hover:text-primary hover:underline"
                                >
                                    {{ receiptNumber(s.receipt_number) }}
                                </Link>
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ fmtDate(s.created_at) }}
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{
                                    PAYMENT_METHOD_LABELS[s.payment_method] ??
                                    s.payment_method
                                }}
                            </td>
                            <td class="px-4 py-2 text-right font-mono">
                                {{ cur(s.total) }}
                            </td>
                            <td class="px-4 py-2">
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
                        </tr>
                    </tbody>
                </table>
                <p v-else class="px-4 py-6 text-sm text-muted-foreground">
                    Todavía no registró compras.
                </p>
            </CardContent>
        </Card>

        <Card class="gap-3 overflow-hidden py-4">
            <CardHeader class="pb-0">
                <CardTitle>Consultas del catálogo</CardTitle>
            </CardHeader>
            <CardContent class="p-0 pt-3">
                <table v-if="client.inquiries.length" class="w-full text-sm">
                    <thead
                        class="admin-table-header text-left text-[11px] tracking-wider uppercase"
                    >
                        <tr>
                            <th class="px-4 py-2">#</th>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2 text-right">Total estimado</th>
                            <th class="w-28 px-4 py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/80">
                        <tr v-for="i in client.inquiries" :key="i.id">
                            <td class="px-4 py-2">
                                <Link
                                    :href="`/admin/inquiries/${i.id}`"
                                    class="font-medium hover:text-primary hover:underline"
                                >
                                    #{{ i.id }}
                                </Link>
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ fmtDate(i.created_at) }}
                            </td>
                            <td class="px-4 py-2 text-right font-mono">
                                {{ cur(i.total_estimated) }}
                            </td>
                            <td class="px-4 py-2">
                                <Badge variant="secondary">
                                    {{ i.status }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="px-4 py-6 text-sm text-muted-foreground">
                    Sin consultas desde el catálogo.
                </p>
            </CardContent>
        </Card>
    </div>
</template>

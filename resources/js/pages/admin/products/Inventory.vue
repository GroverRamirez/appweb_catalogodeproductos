<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Boxes } from 'lucide-vue-next';
import { computed } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/composables/usePermissions';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Productos', href: '/admin/products' },
            { title: 'Inventario', href: '' },
        ],
    }),
});

type Movement = {
    id: number;
    type: string;
    quantity: number;
    previous_stock: number;
    resulting_stock: number;
    reason: string | null;
    author: string | null;
    created_at: string | null;
};

const props = defineProps<{
    product: {
        id: number;
        name: string;
        code: string;
        stock: number;
        min_stock: number;
        unit: string;
    };
    movements: {
        data: Movement[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
}>();

const { can } = usePermissions();

const TYPE_LABELS: Record<string, string> = {
    compra: 'Compra',
    venta: 'Venta',
    anulacion_compra: 'Anulación de compra',
    anulacion_venta: 'Anulación de venta',
    ajuste: 'Ajuste',
};

const label = (type: string) => TYPE_LABELS[type] ?? type;

const fmtDate = (value: string | null) => {
    if (!value) {
        return '—';
    }

    const d = new Date(value);
    const pad = (x: number) => String(x).padStart(2, '0');

    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const isLow = computed(() => props.product.stock <= props.product.min_stock);

const form = useForm({
    new_stock: props.product.stock,
    reason: '',
});

const difference = computed(
    () => (Number(form.new_stock) || 0) - props.product.stock,
);

const submit = () =>
    form.post(`/admin/products/${props.product.id}/inventario`, {
        preserveScroll: true,
        onSuccess: () => form.reset('reason'),
    });
</script>

<template>
    <Head :title="`Inventario · ${product.name}`" />

    <div class="w-full space-y-4 p-3 md:p-4">
        <div class="flex items-center gap-2">
            <Button variant="ghost" size="icon-sm" as-child>
                <Link :href="`/admin/products/${product.id}/edit`">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <PageHeader
                :icon="Boxes"
                eyebrow="Inventario"
                :title="product.name"
                :description="`Código ${product.code}`"
                class="flex-1 border-0 pb-0"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
            <div class="space-y-4">
                <Card class="gap-3 py-4">
                    <CardContent class="space-y-1">
                        <p class="text-xs text-muted-foreground uppercase">
                            Stock actual
                        </p>
                        <p class="text-3xl font-semibold tabular-nums">
                            {{ product.stock }}
                            <span
                                class="text-base font-normal text-muted-foreground"
                            >
                                {{ product.unit }}
                            </span>
                        </p>
                        <Badge v-if="isLow" variant="secondary">
                            En el mínimo ({{ product.min_stock }})
                        </Badge>
                    </CardContent>
                </Card>

                <Card v-if="can('inventory.adjust')" class="gap-3 py-4">
                    <CardHeader class="pb-0">
                        <CardTitle>Ajustar stock</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-3" @submit.prevent="submit">
                            <div class="space-y-1.5">
                                <Label for="new_stock">Stock contado</Label>
                                <Input
                                    id="new_stock"
                                    v-model.number="form.new_stock"
                                    type="number"
                                    min="0"
                                    :aria-invalid="!!form.errors.new_stock"
                                />
                                <InputError :message="form.errors.new_stock" />
                                <p
                                    v-if="difference !== 0"
                                    class="text-xs text-muted-foreground"
                                >
                                    Se registrará un movimiento de
                                    <span class="font-medium tabular-nums">
                                        {{ difference > 0 ? '+' : ''
                                        }}{{ difference }}
                                    </span>
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="reason">Motivo</Label>
                                <Input
                                    id="reason"
                                    v-model="form.reason"
                                    placeholder="Ej. conteo físico de estantería"
                                    :aria-invalid="!!form.errors.reason"
                                />
                                <InputError :message="form.errors.reason" />
                            </div>
                            <Button
                                type="submit"
                                class="w-full"
                                :disabled="form.processing || difference === 0"
                            >
                                Registrar ajuste
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <Card class="gap-3 overflow-hidden py-4">
                <CardHeader class="pb-0">
                    <CardTitle>Movimientos</CardTitle>
                </CardHeader>
                <CardContent class="p-0 pt-3">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead
                                class="admin-table-header text-left text-[11px] tracking-wider uppercase"
                            >
                                <tr>
                                    <th class="px-4 py-2">Fecha</th>
                                    <th class="px-4 py-2">Tipo</th>
                                    <th class="px-4 py-2 text-right">
                                        Cantidad
                                    </th>
                                    <th class="px-4 py-2 text-right">Saldo</th>
                                    <th class="px-4 py-2">Motivo</th>
                                    <th class="px-4 py-2">Usuario</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/80">
                                <tr
                                    v-for="m in movements.data"
                                    :key="m.id"
                                    class="align-top"
                                >
                                    <td
                                        class="px-4 py-3 whitespace-nowrap tabular-nums"
                                    >
                                        {{ fmtDate(m.created_at) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ label(m.type) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono"
                                        :class="
                                            m.quantity < 0
                                                ? 'text-destructive'
                                                : 'text-emerald-600 dark:text-emerald-400'
                                        "
                                    >
                                        {{ m.quantity > 0 ? '+' : ''
                                        }}{{ m.quantity }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-muted-foreground"
                                    >
                                        {{ m.previous_stock }} →
                                        <span class="text-foreground">
                                            {{ m.resulting_stock }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ m.reason ?? '—' }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-muted-foreground"
                                    >
                                        {{ m.author ?? '—' }}
                                    </td>
                                </tr>
                                <tr v-if="!movements.data.length">
                                    <td
                                        colspan="6"
                                        class="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        Este producto todavía no registra
                                        movimientos.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Pagination
            :links="movements.links"
            :from="movements.from"
            :to="movements.to"
            :total="movements.total"
        />
    </div>
</template>

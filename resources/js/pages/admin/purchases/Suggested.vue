<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Copy,
    Download,
    PackagePlus,
    Truck,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatPrice } from '@/lib/catalog';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Compras', href: '/admin/purchases' },
            { title: 'Reposición sugerida', href: '' },
        ],
    }),
});

type SuggestedItem = {
    product_id: number;
    code: string;
    name: string;
    stock: number;
    min_stock: number;
    suggested_quantity: number;
    unit_cost: number;
    subtotal: number;
};

type SupplierGroup = {
    supplier: {
        id: number | null;
        name: string;
        phone: string | null;
        email: string | null;
    };
    items: SuggestedItem[];
    total_suggested_units: number;
    total_estimated_cost: number;
};

defineProps<{
    groups: SupplierGroup[];
    totalProducts: number;
    totalUnits: number;
    totalCost: number;
}>();

const cur = (n: number) => formatPrice(n, 'USD');

const copiedSupplierId = ref<number | string | null>(null);

const generateDraftPurchase = (group: SupplierGroup) => {
    const itemsPayload = group.items.map((i) => ({
        product_id: i.product_id,
        quantity: i.suggested_quantity,
        unit_cost: i.unit_cost,
    }));

    router.get('/admin/purchases/create', {
        supplier_id: group.supplier.id,
        items: JSON.stringify(itemsPayload),
    });
};

const copyWhatsAppMessage = (group: SupplierGroup) => {
    let text = `*PEDIDO DE REPOSICIÓN - ${group.supplier.name}*\n\n`;
    text += `Hola! Deseamos solicitar cotización / reposición de los siguientes productos:\n\n`;

    group.items.forEach((item, idx) => {
        text += `${idx + 1}. [${item.code}] ${item.name} - *Cant: ${item.suggested_quantity}*\n`;
    });

    text += `\n*Total unidades solicitadas:* ${group.total_suggested_units}`;

    if (group.total_estimated_cost > 0) {
        text += `\n*Estimado previo:* ${cur(group.total_estimated_cost)}`;
    }

    navigator.clipboard.writeText(text);
    const key = group.supplier.id ?? 'unassigned';
    copiedSupplierId.value = key;
    setTimeout(() => {
        if (copiedSupplierId.value === key) {
            copiedSupplierId.value = null;
        }
    }, 2500);
};
</script>

<template>
    <Head title="Orden de Compra Sugerida" />

    <div class="space-y-6 p-4 md:p-6 xl:p-8">
        <!-- Encabezado -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="icon-sm" as-child>
                        <Link href="/admin/purchases">
                            <ArrowLeft class="size-4" />
                        </Link>
                    </Button>
                    <h1 class="font-display text-2xl font-bold tracking-tight">
                        Orden de Compra Sugerida
                    </h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    Productos con stock bajo agrupados por distribuidor para
                    reposición inteligente.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button v-if="groups.length > 0" variant="outline" as-child>
                    <a href="/admin/purchases/suggested/export" target="_blank">
                        <Download class="size-4" /> Exportar todo (CSV)
                    </a>
                </Button>
                <Button as-child>
                    <Link href="/admin/purchases/create">
                        <PackagePlus class="size-4" /> Nueva compra manual
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Resumen de faltantes -->
        <div class="grid gap-4 sm:grid-cols-3">
            <Card class="rounded-xl border shadow-xs">
                <CardHeader class="pb-2">
                    <p
                        class="text-xs font-semibold text-muted-foreground uppercase"
                    >
                        Productos por reponer
                    </p>
                    <p class="font-display text-2xl font-bold">
                        {{ totalProducts }}
                    </p>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    En o por debajo de su stock mínimo
                </CardContent>
            </Card>

            <Card class="rounded-xl border shadow-xs">
                <CardHeader class="pb-2">
                    <p
                        class="text-xs font-semibold text-muted-foreground uppercase"
                    >
                        Unidades sugeridas
                    </p>
                    <p
                        class="font-display text-2xl font-bold text-amber-600 dark:text-amber-400"
                    >
                        {{ totalUnits }}
                    </p>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Calculadas para restaurar margen de seguridad
                </CardContent>
            </Card>

            <Card class="rounded-xl border shadow-xs">
                <CardHeader class="pb-2">
                    <p
                        class="text-xs font-semibold text-muted-foreground uppercase"
                    >
                        Inversión estimada
                    </p>
                    <p class="font-display text-2xl font-bold text-primary">
                        {{ cur(totalCost) }}
                    </p>
                </CardHeader>
                <CardContent class="text-xs text-muted-foreground">
                    Basada en últimos costos de compra registrados
                </CardContent>
            </Card>
        </div>

        <!-- Estado vacío: todo en orden -->
        <div
            v-if="groups.length === 0"
            class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card p-12 text-center"
        >
            <span
                class="mb-3 grid size-12 place-items-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20"
            >
                <Check class="size-6" />
            </span>
            <h2 class="text-lg font-bold">¡Inventario en niveles óptimos!</h2>
            <p class="mt-1 max-w-md text-sm text-muted-foreground">
                No hay ningún producto activo que esté en o por debajo de su
                stock mínimo configurado.
            </p>
            <Button variant="outline" class="mt-4" as-child>
                <Link href="/admin/products"> Ver todos los productos </Link>
            </Button>
        </div>

        <!-- Grupos por Proveedor -->
        <div v-else class="space-y-6">
            <Card
                v-for="group in groups"
                :key="group.supplier.id ?? 'unassigned'"
                class="overflow-hidden rounded-xl border shadow-xs"
            >
                <CardHeader
                    class="flex flex-col gap-3 border-b border-border/80 bg-muted/20 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <span
                                class="grid size-8 place-items-center rounded-lg bg-primary/10 text-primary"
                            >
                                <Truck class="size-4" />
                            </span>
                            <div>
                                <CardTitle class="text-base font-bold">
                                    {{ group.supplier.name }}
                                </CardTitle>
                                <p
                                    v-if="
                                        group.supplier.phone ||
                                        group.supplier.email
                                    "
                                    class="text-xs text-muted-foreground"
                                >
                                    {{
                                        [
                                            group.supplier.phone,
                                            group.supplier.email,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones del grupo -->
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="copyWhatsAppMessage(group)"
                        >
                            <Check
                                v-if="
                                    copiedSupplierId ===
                                    (group.supplier.id ?? 'unassigned')
                                "
                                class="size-3.5 text-emerald-600"
                            />
                            <Copy v-else class="size-3.5" />
                            {{
                                copiedSupplierId ===
                                (group.supplier.id ?? 'unassigned')
                                    ? '¡Copiado!'
                                    : 'Copiar pedido'
                            }}
                        </Button>

                        <Button
                            v-if="group.supplier.id"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <a
                                :href="`/admin/purchases/suggested/export?supplier_id=${group.supplier.id}`"
                                target="_blank"
                            >
                                <Download class="size-3.5" /> CSV
                            </a>
                        </Button>

                        <Button size="sm" @click="generateDraftPurchase(group)">
                            <PackagePlus class="size-3.5" />
                            Generar compra borrador
                        </Button>
                    </div>
                </CardHeader>

                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead
                                class="admin-table-header text-left text-[11px] tracking-wider uppercase"
                            >
                                <tr>
                                    <th class="px-4 py-3">Código</th>
                                    <th class="px-4 py-3">Producto</th>
                                    <th class="px-4 py-3 text-right">
                                        Stock Actual
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Stock Mín.
                                    </th>
                                    <th
                                        class="px-4 py-3 text-right font-semibold text-primary"
                                    >
                                        Sugerido
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Costo Estim.
                                    </th>
                                    <th class="px-4 py-3 text-right">
                                        Subtotal
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/80">
                                <tr
                                    v-for="item in group.items"
                                    :key="item.product_id"
                                    class="transition hover:bg-muted/30"
                                >
                                    <td
                                        class="px-4 py-3 font-mono text-xs text-muted-foreground"
                                    >
                                        {{ item.code }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Link
                                            :href="`/admin/products/${item.product_id}/edit`"
                                            class="font-medium hover:text-primary hover:underline"
                                        >
                                            {{ item.name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Badge
                                            :variant="
                                                item.stock <= 0
                                                    ? 'danger'
                                                    : 'warn'
                                            "
                                        >
                                            {{ item.stock }}
                                        </Badge>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-muted-foreground"
                                    >
                                        {{ item.min_stock }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-bold text-primary"
                                    >
                                        +{{ item.suggested_quantity }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-muted-foreground"
                                    >
                                        {{ cur(item.unit_cost) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono font-semibold"
                                    >
                                        {{ cur(item.subtotal) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr
                                    class="border-t border-border bg-muted/10 font-semibold"
                                >
                                    <td
                                        colspan="4"
                                        class="px-4 py-3 text-right text-xs text-muted-foreground uppercase"
                                    >
                                        Total proveedor:
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-primary"
                                    >
                                        {{ group.total_suggested_units }} u.
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-xs text-muted-foreground"
                                    >
                                        Estimado:
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-mono text-base text-foreground"
                                    >
                                        {{ cur(group.total_estimated_cost) }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Eye,
    MessageSquare,
    Package,
    PackagePlus,
    PackageX,
    Receipt,
    ShoppingCart,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import SpendTrendChart from '@/components/admin/SpendTrendChart.vue';
import StatCard from '@/components/admin/StatCard.vue';
import ViewsTrendChart from '@/components/admin/ViewsTrendChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/composables/usePermissions';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({
    layout: () => ({
        breadcrumbs: [{ title: 'Panel', href: '/admin' }],
    }),
});

type Stats = {
    products_total: number;
    products_active: number;
    products_low_stock: number;
    products_out_of_stock: number;
    products_without_cost: number;
    inventory_cost: number;
    inventory_retail: number;
    inquiries_pending: number;
    inquiries_total: number;
    purchases_spend_30d: number;
    purchases_count_30d: number;
    sales_revenue_30d: number;
    sales_count_30d: number;
};

type ProductRow = {
    id: number;
    name: string;
    code: string;
    views_count?: number;
    stock?: number;
    min_stock?: number;
};

type InquiryRow = {
    id: number;
    customer_name: string;
    customer_phone: string;
    status: string;
    source: string;
    created_at: string;
};

type PurchaseRow = {
    id: number;
    reference_number: string | null;
    status: string;
    total_cost: string;
    items_count: number;
    created_at: string;
    supplier: { id: number; name: string } | null;
};

const props = defineProps<{
    stats: Stats;
    topProducts: ProductRow[];
    lowStockProducts: ProductRow[];
    recentInquiries: InquiryRow[];
    recentPurchases: PurchaseRow[];
    viewsLast7Days: { day: string; total: number }[];
    spendByMonth: { month: string; total: number }[];
}>();

const { can } = usePermissions();
const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: string | number) => formatPrice(n, store.value.currency_symbol);

// Margen potencial: lo que dejaría vender todo el stock al precio de lista,
// menos lo que costó. Solo tiene sentido si hay costos cargados.
const potentialMargin = computed(
    () => props.stats.inventory_retail - props.stats.inventory_cost,
);

const costCoverage = computed(() => {
    const total = props.stats.products_total;

    if (!total) {
        return 0;
    }

    return Math.round(
        ((total - props.stats.products_without_cost) / total) * 100,
    );
});

const statusColor = (s: string) => {
    switch (s) {
        case 'pendiente':
            return 'warn';
        case 'contactado':
            return 'info';
        case 'vendido':
            return 'success';
        default:
            return 'secondary';
    }
};
</script>

<template>
    <Head title="Panel administrativo" />

    <div class="space-y-8 p-4 md:p-8">
        <!-- Hero del panel -->
        <div
            class="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-6"
        >
            <div>
                <p
                    class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Panel administrativo
                </p>
                <h1 class="font-display text-2xl font-bold md:text-3xl">
                    Bienvenido de vuelta
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Resumen del catálogo, stock y consultas en tiempo real.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button as-child class="rounded-md px-4">
                    <Link href="/admin/products/create">
                        <Package class="size-4" /> Nuevo producto
                    </Link>
                </Button>
                <Button
                    v-if="can('inventory.adjust')"
                    as-child
                    variant="outline"
                    class="rounded-md px-4"
                >
                    <Link href="/admin/purchases/create">
                        <PackagePlus class="size-4" /> Registrar compra
                    </Link>
                </Button>
                <Button as-child variant="outline" class="rounded-md px-4">
                    <Link href="/admin/inquiries">
                        <MessageSquare class="size-4" /> Ver consultas
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Aviso de calidad de dato: sin costo no hay valorización confiable -->
        <Link
            v-if="can('inventory.view') && stats.products_without_cost > 0"
            href="/admin/products/costs"
            class="flex items-start gap-3 rounded-xl border border-amber-500/45 bg-amber-50/60 p-4 transition hover:border-amber-500/70 dark:border-amber-900/50 dark:bg-amber-500/10"
        >
            <span
                class="grid size-9 shrink-0 place-items-center rounded-lg bg-amber-600 text-white"
            >
                <AlertTriangle class="size-4" />
            </span>
            <span class="flex-1 text-sm">
                <span class="block font-semibold">
                    {{ stats.products_without_cost }} de
                    {{ stats.products_total }} productos no tienen costo cargado
                </span>
                <span class="text-muted-foreground">
                    El valor del inventario y el margen de abajo se calculan
                    solo con el {{ costCoverage }}% del catálogo que sí lo
                    tiene. Tocá acá para cargarlos y que las cifras sean reales.
                </span>
            </span>
            <ArrowRight class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
        </Link>

        <!-- KPIs de dinero -->
        <div
            v-if="can('inventory.view')"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <StatCard
                label="Valor del inventario"
                :value="cur(stats.inventory_cost)"
                hint="Stock actual valorizado a costo"
                :icon="Wallet"
            />
            <StatCard
                label="Compras (30 días)"
                :value="cur(stats.purchases_spend_30d)"
                :hint="`${stats.purchases_count_30d} compras registradas`"
                :icon="ShoppingCart"
            />
            <StatCard
                v-if="can('sales.view')"
                label="Ventas (30 días)"
                :value="cur(stats.sales_revenue_30d)"
                :hint="`${stats.sales_count_30d} ventas confirmadas`"
                tone="success"
                :icon="Receipt"
            />
            <StatCard
                label="Margen potencial"
                :value="cur(potentialMargin)"
                hint="Si se vendiera todo el stock a precio de lista"
                tone="success"
                :icon="TrendingUp"
            />
        </div>

        <!-- KPIs de operación -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
                label="Productos activos"
                :value="`${stats.products_active} / ${stats.products_total}`"
                hint="Visibles en el catálogo"
                :icon="Package"
            />
            <StatCard
                label="Stock bajo"
                :value="stats.products_low_stock"
                hint="Productos por reponer"
                tone="warn"
                :icon="AlertTriangle"
            />
            <StatCard
                label="Sin stock"
                :value="stats.products_out_of_stock"
                hint="Agotados en el catálogo"
                tone="danger"
                :icon="PackageX"
            />
            <StatCard
                label="Consultas pendientes"
                :value="stats.inquiries_pending"
                :hint="`${stats.inquiries_total} en total`"
                tone="warn"
                :icon="MessageSquare"
            />
        </div>

        <!-- Gráficos -->
        <div class="grid gap-6 lg:grid-cols-2">
            <Card class="admin-card overflow-hidden rounded-xl border">
                <CardHeader
                    class="admin-card-header flex flex-row items-center justify-between space-y-0"
                >
                    <CardTitle class="flex items-center gap-2 font-display">
                        <span
                            class="grid size-9 place-items-center rounded-lg bg-primary text-primary-foreground shadow-sm"
                        >
                            <Eye class="size-4" />
                        </span>
                        Visitas del catálogo
                    </CardTitle>
                    <Link
                        href="/admin/reportes"
                        class="group inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                    >
                        Ver reportes
                        <ArrowRight
                            class="size-3 transition-transform group-hover:translate-x-1"
                        />
                    </Link>
                </CardHeader>
                <CardContent class="pt-6">
                    <ViewsTrendChart :data="viewsLast7Days" />
                </CardContent>
            </Card>

            <Card
                v-if="can('inventory.view')"
                class="admin-card overflow-hidden rounded-xl border"
            >
                <CardHeader
                    class="admin-card-header flex flex-row items-center justify-between space-y-0"
                >
                    <CardTitle class="flex items-center gap-2 font-display">
                        <span
                            class="grid size-9 place-items-center rounded-lg bg-primary text-primary-foreground shadow-sm"
                        >
                            <ShoppingCart class="size-4" />
                        </span>
                        Gasto en compras
                    </CardTitle>
                    <Link
                        href="/admin/purchases"
                        class="group inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                    >
                        Ver compras
                        <ArrowRight
                            class="size-3 transition-transform group-hover:translate-x-1"
                        />
                    </Link>
                </CardHeader>
                <CardContent class="pt-6">
                    <SpendTrendChart :data="spendByMonth" :format="cur" />
                </CardContent>
            </Card>
        </div>

        <!-- Tarjetas inferiores -->
        <div class="grid gap-6 lg:grid-cols-2">
            <Card class="admin-card overflow-hidden rounded-xl border">
                <CardHeader class="admin-card-header">
                    <CardTitle class="flex items-center gap-2 font-display">
                        <span
                            class="grid size-9 place-items-center rounded-lg bg-primary text-primary-foreground shadow-sm"
                        >
                            <Eye class="size-4" />
                        </span>
                        Más consultados
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <table v-if="topProducts.length" class="w-full text-sm">
                        <tbody class="divide-y divide-border/80">
                            <tr
                                v-for="p in topProducts"
                                :key="p.id"
                                class="transition hover:bg-accent/45"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="`/admin/products/${p.id}/edit`"
                                        class="flex items-center gap-3"
                                    >
                                        <span
                                            class="grid size-8 shrink-0 place-items-center rounded-md bg-muted text-muted-foreground"
                                        >
                                            <Package class="size-4" />
                                        </span>
                                        <span>
                                            <span
                                                class="font-medium hover:text-primary hover:underline"
                                            >
                                                {{ p.name }}
                                            </span>
                                            <span
                                                class="block text-xs text-muted-foreground"
                                            >
                                                {{ p.code }}
                                            </span>
                                        </span>
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span
                                        class="font-mono font-semibold text-primary"
                                    >
                                        {{ p.views_count }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        vistas</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 px-4 py-10 text-center"
                    >
                        <Eye class="size-6 text-muted-foreground/50" />
                        <p class="text-sm text-muted-foreground">
                            Aún no hay vistas registradas.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="admin-card overflow-hidden rounded-xl border">
                <CardHeader
                    class="admin-card-header flex flex-row items-center justify-between space-y-0"
                >
                    <CardTitle class="flex items-center gap-2 font-display">
                        <span
                            class="grid size-9 place-items-center rounded-lg bg-amber-600 text-white shadow-sm"
                        >
                            <AlertTriangle class="size-4" />
                        </span>
                        Stock bajo
                    </CardTitle>
                    <Link
                        v-if="can('inventory.adjust')"
                        href="/admin/purchases/create"
                        class="text-xs font-semibold text-primary hover:underline"
                    >
                        Registrar compra
                    </Link>
                </CardHeader>
                <CardContent class="p-0">
                    <table
                        v-if="lowStockProducts.length"
                        class="w-full text-sm"
                    >
                        <tbody class="divide-y divide-border/80">
                            <tr
                                v-for="p in lowStockProducts"
                                :key="p.id"
                                class="transition hover:bg-amber-50 dark:hover:bg-amber-500/10"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="`/admin/products/${p.id}/edit`"
                                        class="flex items-center gap-3"
                                    >
                                        <span
                                            class="grid size-8 shrink-0 place-items-center rounded-md bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"
                                        >
                                            <Package class="size-4" />
                                        </span>
                                        <span>
                                            <span
                                                class="font-medium hover:text-amber-700 hover:underline dark:hover:text-amber-300"
                                            >
                                                {{ p.name }}
                                            </span>
                                            <span
                                                class="block text-xs text-muted-foreground"
                                            >
                                                {{ p.code }}
                                            </span>
                                        </span>
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Badge
                                        :variant="
                                            p.stock! <= 0 ? 'danger' : 'warn'
                                        "
                                    >
                                        {{ p.stock }} / mín {{ p.min_stock }}
                                    </Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 px-4 py-10 text-center"
                    >
                        <AlertTriangle
                            class="size-6 text-muted-foreground/50"
                        />
                        <p class="text-sm text-muted-foreground">
                            Todo el stock está sobre el umbral.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card
                v-if="can('inventory.view')"
                class="admin-card overflow-hidden rounded-xl border"
            >
                <CardHeader
                    class="admin-card-header flex flex-row items-center justify-between space-y-0"
                >
                    <CardTitle class="flex items-center gap-2 font-display">
                        <span
                            class="grid size-9 place-items-center rounded-lg bg-primary text-primary-foreground shadow-sm"
                        >
                            <PackagePlus class="size-4" />
                        </span>
                        Últimas compras
                    </CardTitle>
                    <Link
                        href="/admin/purchases"
                        class="group inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                    >
                        Ver todas
                        <ArrowRight
                            class="size-3 transition-transform group-hover:translate-x-1"
                        />
                    </Link>
                </CardHeader>
                <CardContent class="p-0">
                    <table v-if="recentPurchases.length" class="w-full text-sm">
                        <tbody class="divide-y divide-border/80">
                            <tr
                                v-for="p in recentPurchases"
                                :key="p.id"
                                class="transition hover:bg-accent/45"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="`/admin/purchases/${p.id}`"
                                        class="font-medium hover:text-primary hover:underline"
                                    >
                                        {{ p.reference_number ?? `#${p.id}` }}
                                    </Link>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{
                                            p.supplier?.name ?? 'Sin proveedor'
                                        }}
                                        · {{ p.items_count }} productos
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-mono font-semibold">
                                        {{ cur(p.total_cost) }}
                                    </span>
                                    <Badge
                                        v-if="p.status === 'anulada'"
                                        variant="secondary"
                                        class="ml-2"
                                    >
                                        Anulada
                                    </Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 px-4 py-10 text-center"
                    >
                        <PackagePlus class="size-6 text-muted-foreground/50" />
                        <p class="text-sm text-muted-foreground">
                            Todavía no registraste compras.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="admin-card overflow-hidden rounded-xl border">
                <CardHeader
                    class="admin-card-header flex flex-row items-center justify-between space-y-0"
                >
                    <CardTitle class="flex items-center gap-2 font-display">
                        <span
                            class="grid size-9 place-items-center rounded-lg bg-emerald-700 text-white shadow-sm"
                        >
                            <MessageSquare class="size-4" />
                        </span>
                        Últimas consultas
                    </CardTitle>
                    <Link
                        href="/admin/inquiries"
                        class="group inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                    >
                        Ver todas
                        <ArrowRight
                            class="size-3 transition-transform group-hover:translate-x-1"
                        />
                    </Link>
                </CardHeader>
                <CardContent class="p-0">
                    <table v-if="recentInquiries.length" class="w-full text-sm">
                        <tbody class="divide-y divide-border/80">
                            <tr
                                v-for="i in recentInquiries"
                                :key="i.id"
                                class="transition hover:bg-accent/45"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="`/admin/inquiries/${i.id}`"
                                        class="font-medium hover:text-primary hover:underline"
                                    >
                                        {{ i.customer_name }}
                                    </Link>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                    >
                                        {{ i.customer_phone }} · {{ i.source }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Badge
                                        :variant="statusColor(i.status) as any"
                                    >
                                        {{ i.status }}
                                    </Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 px-4 py-10 text-center"
                    >
                        <MessageSquare
                            class="size-6 text-muted-foreground/50"
                        />
                        <p class="text-sm text-muted-foreground">
                            No hay consultas todavía.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

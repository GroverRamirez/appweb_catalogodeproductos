<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Boxes,
    Eye,
    MessageSquare,
    Package,
    PackageX,
    Sparkles,
    Tag,
    Tags,
} from 'lucide-vue-next';
import StatCard from '@/components/admin/StatCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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
    categories_total: number;
    brands_total: number;
    inquiries_pending: number;
    inquiries_total: number;
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

defineProps<{
    stats: Stats;
    topProducts: ProductRow[];
    lowStockProducts: ProductRow[];
    recentInquiries: InquiryRow[];
    viewsLast7Days: { day: string; total: number }[];
}>();

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
        <div class="admin-card relative overflow-hidden rounded-xl border p-6 md:p-8">
            <div class="absolute inset-0 gradient-brand-soft"></div>
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -right-8 -top-8 size-44 rounded-full bg-primary/15 blur-3xl"
            ></div>
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-8 -left-8 size-32 rounded-full bg-accent2/18 blur-3xl"
            ></div>
            <div class="relative flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="mb-1 inline-flex items-center gap-1 text-xs font-bold uppercase tracking-widest text-brand">
                        <Sparkles class="size-3" /> Panel administrativo
                    </p>
                    <h1 class="font-display text-3xl font-bold md:text-4xl">
                        <span class="gradient-text">Bienvenido</span> de vuelta
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Resumen del catálogo, stock y consultas en tiempo real.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child class="rounded-full px-4">
                        <Link href="/admin/products/create">
                            <Package class="size-4" /> Nuevo producto
                        </Link>
                    </Button>
                    <Button as-child variant="outline" class="rounded-full px-4">
                        <Link href="/admin/inquiries">
                            <MessageSquare class="size-4" /> Ver consultas
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- KPIs -->
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
            <StatCard
                label="Categorías"
                :value="stats.categories_total"
                :icon="Tags"
            />
            <StatCard
                label="Marcas"
                :value="stats.brands_total"
                :icon="Tag"
            />
            <StatCard
                label="Visitas (7 días)"
                :value="viewsLast7Days.reduce((a, b) => a + b.total, 0)"
                tone="success"
                :icon="Eye"
            />
            <StatCard
                label="Productos totales"
                :value="stats.products_total"
                :icon="Boxes"
            />
        </div>

        <!-- Tarjetas inferiores -->
        <div class="grid gap-6 lg:grid-cols-2">
            <Card class="admin-card overflow-hidden rounded-xl border">
                <CardHeader class="border-b bg-muted/70">
                    <CardTitle class="flex items-center gap-2 font-display">
                        <span class="grid size-9 place-items-center rounded-lg bg-primary text-primary-foreground shadow-sm">
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
                                        class="font-medium hover:text-primary hover:underline"
                                    >
                                        {{ p.name }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">
                                        {{ p.code }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-mono font-semibold text-primary">
                                        {{ p.views_count }}
                                    </span>
                                    <span class="text-xs text-muted-foreground"> vistas</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="px-4 py-6 text-sm text-muted-foreground">
                        Aún no hay vistas registradas.
                    </p>
                </CardContent>
            </Card>

            <Card class="admin-card overflow-hidden rounded-xl border">
                <CardHeader class="border-b bg-muted/70">
                    <CardTitle class="flex items-center gap-2 font-display">
                        <span class="grid size-9 place-items-center rounded-lg bg-amber-600 text-white shadow-sm">
                            <AlertTriangle class="size-4" />
                        </span>
                        Stock bajo
                    </CardTitle>
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
                                        class="font-medium hover:text-amber-700 hover:underline dark:hover:text-amber-300"
                                    >
                                        {{ p.name }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">
                                        {{ p.code }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Badge
                                        :variant="
                                            p.stock! <= 0
                                                ? 'danger'
                                                : 'warn'
                                        "
                                    >
                                        {{ p.stock }} / mín {{ p.min_stock }}
                                    </Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="px-4 py-6 text-sm text-muted-foreground">
                        Todo el stock está sobre el umbral.
                    </p>
                </CardContent>
            </Card>

            <Card class="admin-card overflow-hidden rounded-xl border lg:col-span-2">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 border-b bg-muted/70">
                    <CardTitle class="flex items-center gap-2 font-display">
                        <span class="grid size-9 place-items-center rounded-lg bg-emerald-700 text-white shadow-sm">
                            <MessageSquare class="size-4" />
                        </span>
                        Últimas consultas
                    </CardTitle>
                    <Link
                        href="/admin/inquiries"
                        class="group inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                    >
                        Ver todas
                        <ArrowRight class="size-3 transition-transform group-hover:translate-x-1" />
                    </Link>
                </CardHeader>
                <CardContent class="p-0">
                    <table
                        v-if="recentInquiries.length"
                        class="w-full text-sm"
                    >
                        <thead class="admin-table-header text-left text-[11px] uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-2">Cliente</th>
                                <th class="px-4 py-2">Teléfono</th>
                                <th class="px-4 py-2">Origen</th>
                                <th class="px-4 py-2">Estado</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/80">
                            <tr
                                v-for="i in recentInquiries"
                                :key="i.id"
                                class="transition hover:bg-accent/45"
                            >
                                <td class="px-4 py-3 font-medium">{{ i.customer_name }}</td>
                                <td class="px-4 py-3">{{ i.customer_phone }}</td>
                                <td class="px-4 py-3 text-xs">{{ i.source }}</td>
                                <td class="px-4 py-3">
                                    <Badge :variant="statusColor(i.status) as any">
                                        {{ i.status }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        :href="`/admin/inquiries/${i.id}`"
                                        class="text-xs font-semibold text-primary hover:underline"
                                    >
                                        Ver →
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="px-4 py-6 text-sm text-muted-foreground">
                        No hay consultas todavía.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

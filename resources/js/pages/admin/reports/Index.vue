<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Calendar,
    DollarSign,
    Download,
    Eye,
    MessageSquare,
    Percent,
} from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';

defineOptions({
    layout: () => ({
        breadcrumbs: [
            { title: 'Panel', href: '/admin' },
            { title: 'Reportes', href: '/admin/reportes' },
        ],
    }),
});

type Range = { from: string; to: string };
type Kpis = {
    total_inquiries: number;
    sold: number;
    pending: number;
    closed: number;
    estimated_revenue: number;
    conversion_rate: number;
};
type Series = { day: string; total: number; revenue: number };
type TopProduct = {
    product_id: number;
    name: string;
    code: string;
    qty: number;
    revenue: number;
};
type TopViewed = {
    product_id: number;
    name: string;
    code: string;
    views: number;
};

const props = defineProps<{
    range: Range;
    kpis: Kpis;
    series: Series[];
    topProducts: TopProduct[];
    topViewed: TopViewed[];
}>();

const page = usePage();
const store = computed(() => page.props.store as StoreSettings);
const cur = (n: number) => formatPrice(n, store.value.currency_symbol);

const filters = reactive({ from: props.range.from, to: props.range.to });

let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) {
        clearTimeout(timer);
    }

    timer = setTimeout(() => {
        router.get(
            '/admin/reportes',
            { ...filters },
            { preserveState: true, replace: true },
        );
    }, 400);
});

const maxInquiries = computed(() =>
    Math.max(1, ...props.series.map((s) => s.total)),
);
const maxRevenue = computed(() =>
    Math.max(1, ...props.series.map((s) => s.revenue)),
);
</script>

<template>
    <Head title="Reportes" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="BarChart3"
            eyebrow="Inteligencia"
            title="Reportes"
            description="Métricas de consultas y conversión en el rango seleccionado."
        >
            <template #actions>
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <Label for="from" class="text-xs">Desde</Label>
                        <Input id="from" type="date" v-model="filters.from" />
                    </div>
                    <div>
                        <Label for="to" class="text-xs">Hasta</Label>
                        <Input id="to" type="date" v-model="filters.to" />
                    </div>
                    <Button variant="outline" as-child class="rounded-full">
                        <a
                            :href="`/admin/reportes/export.csv?from=${filters.from}&to=${filters.to}`"
                        >
                            <Download class="size-4" /> CSV
                        </a>
                    </Button>
                </div>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
                label="Consultas totales"
                :value="kpis.total_inquiries"
                :icon="MessageSquare"
            />
            <StatCard
                label="Vendidas"
                :value="kpis.sold"
                tone="success"
                :icon="BarChart3"
            />
            <StatCard
                label="Conversión"
                :value="`${kpis.conversion_rate}%`"
                hint="vendidas / totales"
                :icon="Percent"
            />
            <StatCard
                label="Ingresos estimados"
                :value="cur(kpis.estimated_revenue)"
                hint="suma de ventas marcadas como vendido"
                tone="success"
                :icon="DollarSign"
            />
            <StatCard
                label="Pendientes"
                :value="kpis.pending"
                tone="warn"
                :icon="MessageSquare"
            />
            <StatCard label="Cerradas" :value="kpis.closed" :icon="Calendar" />
        </div>

        <Card>
            <CardHeader
                ><CardTitle>Consultas e ingresos por día</CardTitle></CardHeader
            >
            <CardContent>
                <div
                    v-if="!series.length"
                    class="text-center text-sm text-muted-foreground"
                >
                    Sin datos en el rango seleccionado.
                </div>
                <div v-else class="space-y-1">
                    <div
                        v-for="row in series"
                        :key="row.day"
                        class="grid grid-cols-[100px_1fr_1fr] items-center gap-3 text-xs"
                    >
                        <span class="text-muted-foreground">
                            {{ row.day }}
                        </span>
                        <div class="flex items-center gap-2">
                            <div
                                class="h-4 rounded bg-sky-400"
                                :style="{
                                    width: `${(row.total / maxInquiries) * 100}%`,
                                }"
                            ></div>
                            <span class="text-sky-700 dark:text-sky-300">{{
                                row.total
                            }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <div
                                class="h-4 rounded bg-emerald-400"
                                :style="{
                                    width: `${(row.revenue / maxRevenue) * 100}%`,
                                }"
                            ></div>
                            <span
                                class="text-emerald-700 dark:text-emerald-300"
                            >
                                {{ cur(row.revenue) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div
                    class="mt-4 flex flex-wrap gap-4 border-t pt-3 text-xs text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1">
                        <span class="size-3 rounded bg-sky-400"></span>
                        Consultas
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="size-3 rounded bg-emerald-400"></span>
                        Ingresos
                    </span>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader
                    ><CardTitle
                        >Productos más solicitados</CardTitle
                    ></CardHeader
                >
                <CardContent>
                    <table v-if="topProducts.length" class="w-full text-sm">
                        <thead
                            class="text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="py-2">Producto</th>
                                <th class="py-2 text-right">Cantidad</th>
                                <th class="py-2 text-right">Ingresos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="p in topProducts" :key="p.product_id">
                                <td class="py-2">
                                    <div class="font-medium">{{ p.name }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ p.code }}
                                    </div>
                                </td>
                                <td class="py-2 text-right">{{ p.qty }}</td>
                                <td class="py-2 text-right">
                                    {{ cur(Number(p.revenue)) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-muted-foreground">
                        Sin consultas con items en este rango.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader
                    ><CardTitle>Productos más vistos</CardTitle></CardHeader
                >
                <CardContent>
                    <table v-if="topViewed.length" class="w-full text-sm">
                        <thead
                            class="text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="py-2">Producto</th>
                                <th class="py-2 text-right">Vistas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="p in topViewed" :key="p.product_id">
                                <td class="py-2">
                                    <div class="font-medium">{{ p.name }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ p.code }}
                                    </div>
                                </td>
                                <td class="py-2 text-right">
                                    <Eye class="mr-1 inline size-3" />
                                    {{ p.views }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-muted-foreground">
                        Sin vistas en este rango.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

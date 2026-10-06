<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import type { ChartConfiguration } from 'chart.js';
import {
    BarChart3,
    Calendar,
    DollarSign,
    Download,
    Eye,
    FileSpreadsheet,
    FileText,
    MessageSquare,
    Percent,
    Receipt,
    ShoppingCart,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';
import ChartCanvas from '@/components/admin/ChartCanvas.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatCard from '@/components/admin/StatCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPrice } from '@/lib/catalog';
import type { StoreSettings } from '@/lib/catalog';
import { shortDay, useChartTheme, withAlpha } from '@/lib/charts';

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
    conversion_rate: number;
    revenue: number;
    sales_count: number;
    average_ticket: number;
    margin: number;
};
type Series = {
    day: string;
    total: number;
    sold: number;
    sales: number;
    revenue: number;
};
type TopProduct = {
    product_id: number | null;
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
type StatusSlice = { status: string; total: number };
type CategorySlice = { category: string; units: number; revenue: number };

const props = defineProps<{
    range: Range;
    kpis: Kpis;
    series: Series[];
    topProducts: TopProduct[];
    topSold: TopProduct[];
    topViewed: TopViewed[];
    statusBreakdown: StatusSlice[];
    byCategory: CategorySlice[];
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

/** Las tres exportaciones salen del mismo rango que se ve en pantalla. */
const exportUrl = (extension: string) =>
    `/admin/reportes/export.${extension}?from=${filters.from}&to=${filters.to}`;

const { palette } = useChartTheme();

const labels = computed(() => props.series.map((row) => shortDay(row.day)));
const hasRevenue = computed(() => props.series.some((row) => row.revenue > 0));
const hasInquiries = computed(() => props.series.some((row) => row.total > 0));

const statusTotal = computed(() =>
    props.statusBreakdown.reduce((sum, slice) => sum + slice.total, 0),
);
const statusLabel = (status: string) =>
    status.charAt(0).toUpperCase() + status.slice(1);

/** Los nombres largos rompen el eje del grafico de barras. */
const short = (text: string, max = 28) =>
    text.length > max ? `${text.slice(0, max - 1)}…` : text;

const topSoldChartData = computed(() => props.topSold.slice(0, 8));

// ── Indicadores graficos ───────────────────────────────────────────────────
// Un solo eje por grafico: ingresos y cantidades se miden en unidades
// distintas, y superponerlos en dos escalas hace que cualquier cruce de lineas
// parezca un hallazgo cuando solo es el efecto de la escala elegida.

const revenueChart = computed<ChartConfiguration<'line'>>(() => ({
    type: 'line',
    data: {
        labels: labels.value,
        datasets: [
            {
                label: 'Ingresos',
                data: props.series.map((row) => row.revenue),
                borderColor: palette.value.series[0],
                backgroundColor: withAlpha(palette.value.series[0]),
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 4,
                tension: 0.3,
                fill: true,
            },
        ],
    },
    options: {
        interaction: { mode: 'index', intersect: false },
        plugins: {
            // Serie unica: el titulo de la tarjeta ya dice que se esta viendo.
            legend: { display: false },
            tooltip: {
                callbacks: { label: (item) => cur(item.parsed.y ?? 0) },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    color: palette.value.muted,
                    maxRotation: 0,
                    maxTicksLimit: 8,
                },
            },
            y: {
                beginAtZero: true,
                grid: { color: palette.value.grid },
                ticks: {
                    color: palette.value.muted,
                    callback: (value) => cur(Number(value)),
                },
            },
        },
    },
}));

const inquiriesChart = computed<ChartConfiguration<'line'>>(() => ({
    type: 'line',
    data: {
        labels: labels.value,
        datasets: [
            {
                label: 'Recibidas',
                data: props.series.map((row) => row.total),
                borderColor: palette.value.series[0],
                backgroundColor: withAlpha(palette.value.series[0]),
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 4,
                tension: 0.3,
                fill: true,
            },
            {
                label: 'Vendidas',
                data: props.series.map((row) => row.sold),
                borderColor: palette.value.series[1],
                backgroundColor: withAlpha(palette.value.series[1]),
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 4,
                tension: 0.3,
                fill: true,
            },
        ],
    },
    options: {
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    color: palette.value.muted,
                    boxWidth: 10,
                    usePointStyle: true,
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: {
                    color: palette.value.muted,
                    maxRotation: 0,
                    maxTicksLimit: 8,
                },
            },
            y: {
                beginAtZero: true,
                grid: { color: palette.value.grid },
                ticks: { color: palette.value.muted, precision: 0 },
            },
        },
    },
}));

const statusChart = computed<ChartConfiguration<'doughnut'>>(() => ({
    type: 'doughnut',
    data: {
        labels: props.statusBreakdown.map((slice) => statusLabel(slice.status)),
        datasets: [
            {
                label: 'Consultas',
                data: props.statusBreakdown.map((slice) => slice.total),
                backgroundColor: props.statusBreakdown.map(
                    (_, index) =>
                        palette.value.series[index % palette.value.series.length],
                ),
                // Separacion de 2px entre porciones: sin ella, dos colores
                // contiguos se leen como una sola porcion.
                borderColor: palette.value.surface,
                borderWidth: 2,
            },
        ],
    },
    options: {
        cutout: '62%',
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (item) => {
                        const value = Number(item.parsed);
                        const share = statusTotal.value
                            ? Math.round((value / statusTotal.value) * 100)
                            : 0;

                        return `${item.label}: ${value} (${share}%)`;
                    },
                },
            },
        },
    },
}));

const topSoldChart = computed<ChartConfiguration<'bar'>>(() => ({
    type: 'bar',
    data: {
        labels: topSoldChartData.value.map((product) => short(product.name)),
        datasets: [
            {
                label: 'Unidades vendidas',
                data: topSoldChartData.value.map((product) => product.qty),
                backgroundColor: palette.value.series[0],
                borderRadius: 4,
                borderSkipped: 'start',
                barThickness: 14,
            },
        ],
    },
    options: {
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: {
                beginAtZero: true,
                grid: { color: palette.value.grid },
                ticks: { color: palette.value.muted, precision: 0 },
            },
            y: {
                grid: { display: false },
                ticks: { color: palette.value.muted },
            },
        },
    },
}));

const categoryChart = computed<ChartConfiguration<'bar'>>(() => ({
    type: 'bar',
    data: {
        labels: props.byCategory.map((row) => short(row.category, 18)),
        datasets: [
            {
                label: 'Ingresos',
                data: props.byCategory.map((row) => row.revenue),
                backgroundColor: palette.value.series[0],
                borderRadius: 4,
                borderSkipped: 'bottom',
                maxBarThickness: 48,
            },
        ],
    },
    options: {
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: { label: (item) => cur(item.parsed.y ?? 0) },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: palette.value.muted, maxRotation: 0 },
            },
            y: {
                beginAtZero: true,
                grid: { color: palette.value.grid },
                ticks: {
                    color: palette.value.muted,
                    callback: (value) => cur(Number(value)),
                },
            },
        },
    },
}));
</script>

<template>
    <Head title="Reportes" />

    <div class="space-y-6 p-4 md:p-6">
        <PageHeader
            :icon="BarChart3"
            eyebrow="Inteligencia"
            title="Reportes"
            description="Consultas, ventas e ingresos reales del rango seleccionado."
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
                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" as-child class="rounded-full">
                            <a :href="exportUrl('pdf')">
                                <FileText class="size-4" /> PDF
                            </a>
                        </Button>
                        <Button variant="outline" as-child class="rounded-full">
                            <a :href="exportUrl('xlsx')">
                                <FileSpreadsheet class="size-4" /> Excel
                            </a>
                        </Button>
                        <Button variant="outline" as-child class="rounded-full">
                            <a :href="exportUrl('csv')">
                                <Download class="size-4" /> CSV
                            </a>
                        </Button>
                    </div>
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
                label="Ingresos"
                :value="cur(kpis.revenue)"
                hint="ventas confirmadas del rango"
                tone="success"
                :icon="DollarSign"
            />
            <StatCard
                label="Ventas"
                :value="kpis.sales_count"
                :icon="ShoppingCart"
            />
            <StatCard
                label="Ticket promedio"
                :value="cur(kpis.average_ticket)"
                hint="ingresos / ventas"
                :icon="Receipt"
            />
            <StatCard
                label="Margen"
                :value="cur(kpis.margin)"
                hint="precio menos costo, descuentos incluidos"
                :tone="kpis.margin >= 0 ? 'success' : 'warn'"
                :icon="TrendingUp"
            />
            <StatCard
                label="Pendientes"
                :value="kpis.pending"
                tone="warn"
                :icon="MessageSquare"
            />
            <StatCard label="Cerradas" :value="kpis.closed" :icon="Calendar" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader><CardTitle>Ingresos por día</CardTitle></CardHeader>
                <CardContent>
                    <ChartCanvas
                        v-if="hasRevenue"
                        :config="revenueChart"
                        label="Ingresos diarios de ventas confirmadas en el rango"
                    />
                    <p
                        v-else
                        class="flex h-[240px] items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground"
                    >
                        Sin ventas confirmadas en el rango.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Consultas por día</CardTitle>
                </CardHeader>
                <CardContent>
                    <ChartCanvas
                        v-if="hasInquiries"
                        :config="inquiriesChart"
                        label="Consultas recibidas y consultas vendidas por día"
                    />
                    <p
                        v-else
                        class="flex h-[240px] items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground"
                    >
                        Sin consultas en el rango.
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardTitle>Consultas por estado</CardTitle>
                </CardHeader>
                <CardContent>
                    <template v-if="statusTotal > 0">
                        <ChartCanvas
                            :config="statusChart"
                            :height="200"
                            label="Distribución de consultas por estado"
                        />
                        <!-- Etiquetas directas: el color solo no alcanza para
                             identificar cada estado. -->
                        <ul class="mt-4 space-y-1 text-sm">
                            <li
                                v-for="(slice, index) in statusBreakdown"
                                :key="slice.status"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="size-3 shrink-0 rounded-sm"
                                    :style="{
                                        backgroundColor:
                                            palette.series[
                                                index % palette.series.length
                                            ],
                                    }"
                                ></span>
                                <span class="flex-1">{{
                                    statusLabel(slice.status)
                                }}</span>
                                <span class="tabular-nums">
                                    {{ slice.total }}
                                    <span class="text-muted-foreground">
                                        ({{
                                            Math.round(
                                                (slice.total / statusTotal) *
                                                    100,
                                            )
                                        }}%)
                                    </span>
                                </span>
                            </li>
                        </ul>
                    </template>
                    <p
                        v-else
                        class="flex h-[200px] items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground"
                    >
                        Sin consultas en el rango.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Unidades vendidas por producto</CardTitle>
                </CardHeader>
                <CardContent>
                    <ChartCanvas
                        v-if="topSoldChartData.length"
                        :config="topSoldChart"
                        :height="280"
                        label="Productos con más unidades vendidas en el rango"
                    />
                    <p
                        v-else
                        class="flex h-[280px] items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground"
                    >
                        Sin ventas en el rango.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Ingresos por categoría</CardTitle>
                </CardHeader>
                <CardContent>
                    <ChartCanvas
                        v-if="byCategory.length"
                        :config="categoryChart"
                        :height="280"
                        label="Ingresos por categoría de producto en el rango"
                    />
                    <p
                        v-else
                        class="flex h-[280px] items-center justify-center rounded-md border border-dashed text-sm text-muted-foreground"
                    >
                        Sin ventas con categoría en el rango.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader><CardTitle>Detalle diario</CardTitle></CardHeader>
            <CardContent>
                <div
                    v-if="!series.length"
                    class="text-center text-sm text-muted-foreground"
                >
                    Sin datos en el rango seleccionado.
                </div>
                <details v-else>
                    <summary
                        class="cursor-pointer text-sm text-muted-foreground"
                    >
                        Ver la tabla del gráfico ({{ series.length }} días)
                    </summary>
                    <table class="mt-3 w-full text-sm">
                        <thead
                            class="text-left text-xs text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="py-2">Fecha</th>
                                <th class="py-2 text-right">Consultas</th>
                                <th class="py-2 text-right">Vendidas</th>
                                <th class="py-2 text-right">Ventas</th>
                                <th class="py-2 text-right">Ingresos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in series" :key="row.day">
                                <td class="py-1.5">{{ row.day }}</td>
                                <td class="py-1.5 text-right">
                                    {{ row.total }}
                                </td>
                                <td class="py-1.5 text-right">
                                    {{ row.sold }}
                                </td>
                                <td class="py-1.5 text-right">
                                    {{ row.sales }}
                                </td>
                                <td class="py-1.5 text-right">
                                    {{ cur(row.revenue) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </details>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardTitle>Productos más vendidos</CardTitle>
                </CardHeader>
                <CardContent>
                    <table v-if="topSold.length" class="w-full text-sm">
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
                            <tr v-for="p in topSold" :key="p.product_id ?? p.name">
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
                        Sin ventas en este rango.
                    </p>
                </CardContent>
            </Card>

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
                            <tr
                                v-for="p in topProducts"
                                :key="p.product_id ?? p.name"
                            >
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

<script setup lang="ts">
import { computed } from 'vue';

type Point = { month: string; total: number };

const props = defineProps<{
    salesData: Point[];
    spendData: Point[];
    format: (value: number) => string;
}>();

type MonthComparison = {
    key: string;
    label: string;
    monthLabel: string;
    sales: number;
    spend: number;
    margin: number;
    marginPercent: number | null;
};

const months = computed<MonthComparison[]>(() => {
    const salesMap = new Map(props.salesData.map((d) => [d.month, d.total]));
    const spendMap = new Map(props.spendData.map((d) => [d.month, d.total]));
    const now = new Date();

    return Array.from({ length: 6 }, (_, i) => {
        const date = new Date(now.getFullYear(), now.getMonth() - (5 - i), 1);
        const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        const sales = salesMap.get(key) ?? 0;
        const spend = spendMap.get(key) ?? 0;
        const margin = sales - spend;
        const marginPercent = sales > 0 ? (margin / sales) * 100 : null;
        const monthLabel = date.toLocaleDateString('es', {
            month: 'long',
            year: 'numeric',
        });

        return {
            key,
            label: date
                .toLocaleDateString('es', { month: 'short' })
                .replace('.', ''),
            monthLabel,
            sales,
            spend,
            margin,
            marginPercent,
        };
    });
});

const totalSales = computed(() =>
    months.value.reduce((acc, m) => acc + m.sales, 0),
);

const totalSpend = computed(() =>
    months.value.reduce((acc, m) => acc + m.spend, 0),
);

const grossMargin = computed(() => totalSales.value - totalSpend.value);

const marginPercent = computed(() => {
    if (totalSales.value <= 0) {
        return null;
    }

    return (grossMargin.value / totalSales.value) * 100;
});

const maxVal = computed(() => {
    const vals = months.value.flatMap((m) => [m.sales, m.spend]);

    return Math.max(...vals, 0);
});

const isEmpty = computed(() => maxVal.value <= 0);
</script>

<template>
    <div>
        <div class="mb-5 flex flex-wrap items-baseline justify-between gap-3">
            <div>
                <div class="flex items-baseline gap-2">
                    <p
                        class="font-display text-3xl font-semibold tracking-tight text-foreground tabular-nums"
                    >
                        {{ format(totalSales) }}
                    </p>
                    <span
                        v-if="marginPercent !== null"
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="
                            grossMargin >= 0
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300'
                                : 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300'
                        "
                    >
                        {{ grossMargin >= 0 ? '+' : ''
                        }}{{ marginPercent.toFixed(1) }}% margen
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">
                    ingresos por ventas · últimos 6 meses (vs.
                    {{ format(totalSpend) }} en compras)
                </p>
            </div>
            <div
                v-if="!isEmpty"
                class="flex items-center gap-3 text-xs text-muted-foreground"
            >
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-emerald-600" />
                    Ventas
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full bg-sky-500" />
                    Compras
                </span>
            </div>
        </div>

        <div
            v-if="isEmpty"
            class="flex h-28 items-center justify-center rounded-md border border-dashed border-border text-sm text-muted-foreground"
        >
            Todavía no hay ventas ni compras registradas en los últimos 6 meses.
        </div>

        <template v-else>
            <div class="flex h-28 items-end justify-between gap-2 sm:gap-4">
                <div
                    v-for="m in months"
                    :key="m.key"
                    class="group relative flex h-full flex-1 flex-col items-center justify-end"
                >
                    <!-- Tooltip al pasar el mouse -->
                    <div
                        class="pointer-events-none absolute bottom-full z-20 mb-2 scale-0 rounded-lg border border-border/80 bg-popover px-3 py-2 text-xs shadow-md transition-all group-hover:scale-100 dark:bg-zinc-900"
                    >
                        <p class="font-semibold text-foreground capitalize">
                            {{ m.monthLabel }}
                        </p>
                        <div class="mt-1 space-y-0.5 tabular-nums">
                            <p class="text-emerald-600 dark:text-emerald-400">
                                Ventas: {{ format(m.sales) }}
                            </p>
                            <p class="text-sky-600 dark:text-sky-400">
                                Compras: {{ format(m.spend) }}
                            </p>
                            <p
                                class="border-t border-border/60 pt-0.5 font-medium"
                                :class="
                                    m.margin >= 0
                                        ? 'text-foreground'
                                        : 'text-destructive'
                                "
                            >
                                Margen: {{ format(m.margin) }}
                                <span
                                    v-if="m.marginPercent !== null"
                                    class="text-[11px]"
                                >
                                    ({{ m.marginPercent >= 0 ? '+' : ''
                                    }}{{ m.marginPercent.toFixed(1) }}%)
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Pareja de barras: Ventas (verde) y Compras (azul) -->
                    <div
                        class="flex h-full w-full max-w-14 items-end justify-center gap-1"
                    >
                        <!-- Barra de ventas -->
                        <div class="flex h-full w-1/2 items-end justify-center">
                            <div
                                v-if="m.sales <= 0"
                                class="h-0.5 w-full rounded-full bg-border"
                            />
                            <div
                                v-else
                                class="w-full rounded-t-[3px] bg-emerald-600 transition-colors group-hover:bg-emerald-500"
                                :style="{
                                    height: `${Math.max((m.sales / maxVal) * 100, 4)}%`,
                                }"
                            />
                        </div>

                        <!-- Barra de compras -->
                        <div class="flex h-full w-1/2 items-end justify-center">
                            <div
                                v-if="m.spend <= 0"
                                class="h-0.5 w-full rounded-full bg-border"
                            />
                            <div
                                v-else
                                class="w-full rounded-t-[3px] bg-sky-500 transition-colors group-hover:bg-sky-400"
                                :style="{
                                    height: `${Math.max((m.spend / maxVal) * 100, 4)}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Etiquetas de mes abajo -->
            <div
                class="mt-2 flex justify-between gap-2 border-t border-border pt-2"
            >
                <span
                    v-for="m in months"
                    :key="m.key"
                    class="flex-1 text-center text-[11px] tracking-wide text-muted-foreground uppercase"
                >
                    {{ m.label }}
                </span>
            </div>

            <!-- Resumen de margen operativo bruto -->
            <div
                class="mt-3 flex items-center justify-between rounded-lg bg-muted/40 px-3 py-2 text-xs"
            >
                <span class="text-muted-foreground">
                    Margen operativo bruto estimado (6m):
                </span>
                <span
                    class="font-mono font-semibold"
                    :class="
                        grossMargin >= 0
                            ? 'text-emerald-700 dark:text-emerald-400'
                            : 'text-destructive'
                    "
                >
                    {{ format(grossMargin) }}
                </span>
            </div>
        </template>
    </div>
</template>

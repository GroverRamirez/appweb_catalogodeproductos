<script setup lang="ts">
import { computed } from 'vue';
import BarTrend from '@/components/admin/BarTrend.vue';
import type { TrendBar } from '@/components/admin/BarTrend.vue';

type Point = { month: string; total: number };

const props = defineProps<{
    data: Point[];
    /** Formateador de moneda inyectado desde la página (usa el símbolo de la tienda) */
    format: (value: number) => string;
}>();

// Completa los 6 meses del rango: el backend solo devuelve meses con compras.
const bars = computed<TrendBar[]>(() => {
    const byMonth = new Map(props.data.map((d) => [d.month, d.total]));
    const now = new Date();

    return Array.from({ length: 6 }, (_, i) => {
        const date = new Date(now.getFullYear(), now.getMonth() - (5 - i), 1);
        const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
        const total = byMonth.get(key) ?? 0;
        const monthLabel = date.toLocaleDateString('es', {
            month: 'long',
            year: 'numeric',
        });

        return {
            key,
            label: date
                .toLocaleDateString('es', { month: 'short' })
                .replace('.', ''),
            value: total,
            display: props.format(total),
            tooltip: `${props.format(total)} · ${monthLabel}`,
        };
    });
});

const total = computed(() => bars.value.reduce((a, b) => a + b.value, 0));
</script>

<template>
    <BarTrend
        :bars="bars"
        :headline="format(total)"
        sub="gastado en compras · últimos 6 meses"
        empty-message="Todavía no hay compras confirmadas en estos 6 meses."
    />
</template>

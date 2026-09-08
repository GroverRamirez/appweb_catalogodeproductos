<script setup lang="ts">
import { computed } from 'vue';
import BarTrend from '@/components/admin/BarTrend.vue';
import type { TrendBar } from '@/components/admin/BarTrend.vue';

type Point = { day: string; total: number };

const props = defineProps<{
    data: Point[];
}>();

// Completa los 7 días del rango (el backend solo manda días con vistas>0),
// para que la línea de base nunca "salte" días sin datos.
const bars = computed<TrendBar[]>(() => {
    const byDay = new Map(props.data.map((d) => [d.day, d.total]));
    const today = new Date();

    return Array.from({ length: 7 }, (_, i) => {
        const date = new Date(today);
        date.setDate(today.getDate() - (6 - i));
        const key = date.toISOString().slice(0, 10);
        const total = byDay.get(key) ?? 0;
        const label = date.toLocaleDateString('es', {
            day: 'numeric',
            month: 'short',
        });

        return {
            key,
            label: date
                .toLocaleDateString('es', { weekday: 'short' })
                .replace('.', ''),
            value: total,
            display: String(total),
            tooltip: `${total} vistas · ${label}`,
        };
    });
});

const total = computed(() => bars.value.reduce((a, b) => a + b.value, 0));
const avg = computed(
    () => Math.round((total.value / bars.value.length) * 10) / 10,
);
</script>

<template>
    <BarTrend
        :bars="bars"
        :headline="String(total)"
        :sub="`vistas en 7 días · promedio ${avg}/día`"
        empty-message="Todavía no hay visitas registradas en estos 7 días."
    />
</template>

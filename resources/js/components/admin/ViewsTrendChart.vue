<script setup lang="ts">
import { computed } from 'vue';

type Point = { day: string; total: number };

const props = defineProps<{
    data: Point[];
}>();

// Completa los 7 días del rango (el backend solo manda días con vistas>0),
// para que la línea de base nunca "salte" días sin datos.
const days = computed(() => {
    const byDay = new Map(props.data.map((d) => [d.day, d.total]));
    const today = new Date();

    return Array.from({ length: 7 }, (_, i) => {
        const date = new Date(today);
        date.setDate(today.getDate() - (6 - i));
        const key = date.toISOString().slice(0, 10);

        return {
            key,
            weekday: date
                .toLocaleDateString('es', { weekday: 'short' })
                .replace('.', ''),
            label: date.toLocaleDateString('es', {
                day: 'numeric',
                month: 'short',
            }),
            total: byDay.get(key) ?? 0,
        };
    });
});

const max = computed(() => Math.max(1, ...days.value.map((d) => d.total)));
const total = computed(() => days.value.reduce((a, d) => a + d.total, 0));
const avg = computed(() => Math.round((total.value / days.value.length) * 10) / 10);
</script>

<template>
    <div>
        <div class="mb-5 flex items-baseline gap-3">
            <p class="font-display text-3xl font-extrabold tracking-tight">
                {{ total }}
            </p>
            <p class="text-sm text-muted-foreground">
                vistas en 7 días · promedio {{ avg }}/día
            </p>
        </div>

        <div class="flex h-24 items-end justify-between gap-2 sm:gap-3">
            <div
                v-for="d in days"
                :key="d.key"
                class="group relative flex h-full flex-1 flex-col items-center justify-end gap-2"
            >
                <!-- Etiqueta directa: solo en el día pico, para no saturar -->
                <span
                    v-if="d.total === max && d.total > 0"
                    class="text-xs font-semibold text-brand"
                >
                    {{ d.total }}
                </span>

                <!-- Tooltip al pasar el mouse -->
                <div
                    class="pointer-events-none absolute bottom-full mb-2 scale-0 rounded-md bg-foreground px-2 py-1 text-xs whitespace-nowrap text-background opacity-0 transition-all group-hover:scale-100 group-hover:opacity-100"
                >
                    {{ d.total }} vistas · {{ d.label }}
                </div>

                <div
                    class="w-full max-w-9 rounded-t-[4px] bg-brand transition-colors group-hover:bg-brand/80"
                    :style="{
                        height: `${Math.max((d.total / max) * 100, 3)}%`,
                    }"
                ></div>
            </div>
        </div>
        <div class="mt-2 flex justify-between gap-2 border-t border-border pt-2">
            <span
                v-for="d in days"
                :key="d.key"
                class="flex-1 text-center text-[11px] tracking-wide text-muted-foreground uppercase"
            >
                {{ d.weekday }}
            </span>
        </div>
    </div>
</template>

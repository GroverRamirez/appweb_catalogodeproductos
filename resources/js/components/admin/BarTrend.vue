<script setup lang="ts">
import { computed } from 'vue';

export type TrendBar = {
    key: string;
    /** Etiqueta corta bajo la barra (ej. "lun", "ago") */
    label: string;
    value: number;
    /** Valor ya formateado para mostrar (ej. "Bs 1.200,00") */
    display: string;
    /** Texto del tooltip al pasar el mouse */
    tooltip: string;
};

const props = defineProps<{
    bars: TrendBar[];
    /** Número grande arriba del gráfico */
    headline: string;
    /** Texto secundario al lado del headline */
    sub: string;
    /** Mensaje cuando todas las barras están en cero */
    emptyMessage: string;
}>();

const max = computed(() => Math.max(...props.bars.map((b) => b.value), 0));
const isEmpty = computed(() => max.value <= 0);
</script>

<template>
    <div>
        <div class="mb-5 flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <p
                class="font-display text-3xl font-semibold tracking-tight tabular-nums"
            >
                {{ headline }}
            </p>
            <p class="text-sm text-muted-foreground">{{ sub }}</p>
        </div>

        <!-- Sin datos: un mensaje explícito es más honesto que barras en cero,
             que se leen como un error de carga. -->
        <div
            v-if="isEmpty"
            class="flex h-24 items-center justify-center rounded-md border border-dashed border-border text-sm text-muted-foreground"
        >
            {{ emptyMessage }}
        </div>

        <template v-else>
            <div class="flex h-24 items-end justify-between gap-2 sm:gap-3">
                <div
                    v-for="b in bars"
                    :key="b.key"
                    class="group relative flex h-full flex-1 flex-col items-center justify-end gap-2"
                >
                    <!-- Etiqueta directa solo en el pico, para no saturar -->
                    <span
                        v-if="b.value === max"
                        class="text-xs font-semibold whitespace-nowrap text-brand"
                    >
                        {{ b.display }}
                    </span>

                    <div
                        class="pointer-events-none absolute bottom-full mb-2 scale-0 rounded-md bg-foreground px-2 py-1 text-xs whitespace-nowrap text-background opacity-0 transition-all group-hover:scale-100 group-hover:opacity-100"
                    >
                        {{ b.tooltip }}
                    </div>

                    <!-- Un periodo en cero se dibuja como una linea de base
                         apagada, no como una barra chica: una barra visible
                         haria leer "hubo algo" donde no hubo nada. -->
                    <div
                        v-if="b.value <= 0"
                        class="h-0.5 w-full max-w-9 rounded-full bg-border"
                    ></div>
                    <div
                        v-else
                        class="w-full max-w-9 rounded-t-[4px] bg-brand transition-colors group-hover:bg-brand/80"
                        :style="{
                            height: `${Math.max((b.value / max) * 100, 4)}%`,
                        }"
                    ></div>
                </div>
            </div>
            <div
                class="mt-2 flex justify-between gap-2 border-t border-border pt-2"
            >
                <span
                    v-for="b in bars"
                    :key="b.key"
                    class="flex-1 text-center text-[11px] tracking-wide text-muted-foreground uppercase"
                >
                    {{ b.label }}
                </span>
            </div>
        </template>
    </div>
</template>

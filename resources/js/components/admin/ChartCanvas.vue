<script setup lang="ts">
import type { ChartConfiguration } from 'chart.js';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { PanelChartConfig } from '@/lib/charts';

/**
 * Lienzo de Chart.js reutilizable.
 *
 * La pagina arma la configuracion completa (tipo, datos y opciones) y este
 * componente se ocupa del ciclo de vida: crear el grafico, actualizarlo cuando
 * cambian los datos o el tema, y destruirlo al desmontar — sin eso, cada
 * navegacion de Inertia deja un grafico vivo escuchando el resize de la
 * ventana.
 */
const props = withDefaults(
    defineProps<{
        config: PanelChartConfig;
        /** Alto del lienzo en pixeles; el ancho lo da el contenedor. */
        height?: number;
        /** Descripcion para lectores de pantalla: un <canvas> no dice nada. */
        label: string;
    }>(),
    { height: 240 },
);

const canvas = ref<HTMLCanvasElement | null>(null);

type ChartInstance = {
    data: unknown;
    options: unknown;
    update: () => void;
    destroy: () => void;
};

let chart: ChartInstance | null = null;

/** El alto lo fija el contenedor, no la relacion de aspecto por defecto. */
const normalize = (config: PanelChartConfig): ChartConfiguration =>
    ({
        ...config,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            ...(config.options ?? {}),
        },
    }) as unknown as ChartConfiguration;

onMounted(async () => {
    // Import dinamico: Chart.js necesita DOM al registrarse y el render SSR no
    // lo tiene. De paso, la libreria no entra en el bundle inicial del panel.
    const { default: Chart } = await import('chart.js/auto');

    if (!canvas.value) {
        return;
    }

    chart = new Chart(
        canvas.value,
        normalize(props.config),
    ) as unknown as ChartInstance;
});

watch(
    () => props.config,
    (config) => {
        if (!chart) {
            return;
        }

        const next = normalize(config);
        chart.data = next.data;
        chart.options = next.options ?? {};
        chart.update();
    },
    { deep: true },
);

onBeforeUnmount(() => {
    chart?.destroy();
    chart = null;
});
</script>

<template>
    <div class="relative w-full" :style="{ height: `${height}px` }">
        <canvas ref="canvas" role="img" :aria-label="label"></canvas>
    </div>
</template>

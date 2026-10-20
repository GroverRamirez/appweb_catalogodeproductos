import type { ChartConfiguration } from 'chart.js';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Paleta de los graficos del panel.
 *
 * Los tonos estan elegidos para que se distingan tambien con daltonismo
 * y sobre las dos superficies: la version oscura no es la clara "aclarada",
 * es su propio escalon. El orden es fijo — el color identifica a la serie, no
 * a su posicion en el ranking, asi que filtrar no debe repintar lo que queda.
 */
const LIGHT = {
    series: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'],
    text: '#0f172a',
    muted: '#64748b',
    grid: '#e2e8f0',
    surface: '#ffffff',
};

const DARK = {
    series: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181'],
    text: '#f1f5f9',
    muted: '#94a3b8',
    grid: '#1e293b',
    surface: '#0f172a',
};

export type ChartPalette = typeof LIGHT;

/**
 * Sigue el tema activo. El modo oscuro se marca con la clase `dark` en <html>
 * (ver useAppearance), asi que se observa ese atributo en vez de consultar el
 * media query: el usuario puede forzar claro u oscuro desde la app.
 */
export function useChartTheme() {
    const isDark = ref(false);
    let observer: MutationObserver | null = null;

    onMounted(() => {
        const read = () => {
            isDark.value = document.documentElement.classList.contains('dark');
        };

        read();
        observer = new MutationObserver(read);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
        observer = null;
    });

    const palette = computed<ChartPalette>(() => (isDark.value ? DARK : LIGHT));

    return { isDark, palette };
}

/** Color de serie con transparencia, para el relleno bajo una linea. */
export function withAlpha(hex: string, alpha = '22'): string {
    return `${hex}${alpha}`;
}

/** Etiqueta corta de fecha para el eje: 2026-09-28 -> 28/09 */
export function shortDay(day: string): string {
    const [, month, date] = day.split('-');

    return `${date}/${month}`;
}

/**
 * Configuraciones que acepta el panel. Se enumeran los tres tipos en uso en
 * vez de usar el generico abierto de Chart.js: asi los callbacks de tooltip y
 * de ejes reciben su tipo exacto y no hay que anotarlos a mano.
 */
export type PanelChartConfig =
    | ChartConfiguration<'line'>
    | ChartConfiguration<'bar'>
    | ChartConfiguration<'doughnut'>;

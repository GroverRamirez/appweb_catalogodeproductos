import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Composable de traducciones. Lee `props.translations` (array PHP de lang/<locale>/catalog.php)
 * compartido vía Inertia.
 *
 * Uso:
 *   const { t, locale } = useTranslations();
 *   t('home') -> "Inicio" / "Home"
 *   t('low_stock', { count: 3 }) -> "¡Últimas 3!"
 */
export function useTranslations() {
    const page = usePage();
    const translations = computed(
        () => (page.props.translations ?? {}) as Record<string, string>,
    );
    const locale = computed(() => (page.props.locale ?? 'es') as string);

    const t = (key: string, params: Record<string, string | number> = {}) => {
        let s = translations.value[key] ?? key;

        for (const [k, v] of Object.entries(params)) {
            s = s.replaceAll(`:${k}`, String(v));
        }

        return s;
    };

    return { t, locale };
}

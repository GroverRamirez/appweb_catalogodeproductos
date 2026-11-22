import { onBeforeUnmount, onMounted } from 'vue';

export type PaperWidth = '58mm' | '80mm';

/**
 * Impresión de comprobantes en impresora térmica (58 mm o 80 mm).
 *
 * Las reglas se inyectan dinámicamente al imprimir y se limpian al salir.
 * El ancho del rollo es configurable (58 mm para mini printers POS y 80 mm para estándar).
 */
const PX_TO_MM = 25.4 / 96;
const SLACK_MM = 6;

const getWidthConfig = (width: PaperWidth) => {
    if (width === '58mm') {
        return {
            widthMm: 58,
            marginMm: 2,
        };
    }

    return {
        widthMm: 80,
        marginMm: 4,
    };
};

const baseCss = (width: PaperWidth, heightMm: number | null) => {
    const { widthMm, marginMm } = getWidthConfig(width);
    const usableWidthMm = widthMm - marginMm * 2;

    return `
@page {
    size: ${widthMm}mm ${heightMm ? `${heightMm}mm` : '297mm'};
    margin: ${marginMm}mm;
}
@media print {
    html, body {
        width: ${usableWidthMm}mm !important;
        min-width: 0 !important;
        min-height: 0 !important;
        background: #fff !important;
    }
    [data-slot='sidebar'], [data-slot='sidebar-gap'], [data-sonner-toaster] {
        display: none !important;
    }
    [data-slot='sidebar-wrapper'], [data-slot='sidebar-inset'] {
        min-height: 0 !important;
    }
    [data-slot='sidebar-inset'] {
        margin: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }
    #print-receipt { display: block !important; }
}`;
};

/** Mide el comprobante oculto con el ancho útil del rollo y lo pasa a mm. */
const measureReceiptMm = (width: PaperWidth): number | null => {
    const el = document.getElementById('print-receipt');

    if (!el) {
        return null;
    }

    const { widthMm, marginMm } = getWidthConfig(width);
    const usableWidthMm = widthMm - marginMm * 2;

    const previous = el.getAttribute('style');
    el.setAttribute(
        'style',
        `display:block;position:absolute;left:-10000px;top:0;visibility:hidden;` +
            `width:${usableWidthMm}mm;max-width:none;` +
            `padding:0;border:0;box-shadow:none;`,
    );
    const heightPx = el.getBoundingClientRect().height;

    if (previous === null) {
        el.removeAttribute('style');
    } else {
        el.setAttribute('style', previous);
    }

    if (!heightPx) {
        return null;
    }

    return Math.ceil(heightPx * PX_TO_MM + marginMm * 2 + SLACK_MM);
};

export function useThermalPrint(
    styleId: string,
    getWidth: () => PaperWidth = () => '80mm',
) {
    const setCss = (heightMm: number | null) => {
        let style = document.getElementById(styleId);

        if (!style) {
            style = document.createElement('style');
            style.id = styleId;
            document.head.appendChild(style);
        }

        style.textContent = baseCss(getWidth(), heightMm);
    };

    // Ctrl+P también pasa por aquí, no solo el botón Imprimir.
    const onBeforePrint = () => setCss(measureReceiptMm(getWidth()));

    onMounted(() => {
        setCss(null);
        window.addEventListener('beforeprint', onBeforePrint);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeprint', onBeforePrint);
        document.getElementById(styleId)?.remove();
    });

    return () => {
        onBeforePrint();
        window.print();
    };
}

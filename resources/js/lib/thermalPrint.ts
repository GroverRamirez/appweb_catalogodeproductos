import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Impresión de comprobantes (ventas y compras) en impresora térmica de 80 mm.
 *
 * Las reglas se inyectan al montar y se quitan al salir. Si vivieran en un
 * bloque <style> del SFC serían globales y persistirían: al ser una SPA, el
 * chunk CSS no se descarga, y después de visitar el detalle cualquier otra
 * pantalla del panel se imprimiría en una tira de 80 mm.
 *
 * El alto de la hoja se calcula al imprimir: `@page { size: 80mm auto }` no es
 * válido (CSS solo admite dos largos o un tamaño con nombre), así que Chrome
 * descartaba la regla y mandaba el ticket centrado en una hoja carta. Con el
 * alto exacto del comprobante sale una sola página del largo justo y la
 * impresora no avanza papel en blanco.
 */
const PAPER_WIDTH_MM = 80;
const PAGE_MARGIN_MM = 4;
const PX_TO_MM = 25.4 / 96;
// Holgura para redondeos y diferencias de fuente entre pantalla e impresión;
// si el contenido supera el alto de la hoja, Chrome agrega una segunda página.
const SLACK_MM = 6;

const baseCss = (heightMm: number | null) => `
@page {
    size: ${PAPER_WIDTH_MM}mm ${heightMm ? `${heightMm}mm` : '297mm'};
    margin: ${PAGE_MARGIN_MM}mm;
}
@media print {
    html, body {
        width: ${PAPER_WIDTH_MM - PAGE_MARGIN_MM * 2}mm !important;
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
    /* El comprobante vive oculto en la pantalla y solo aparece al imprimir.
       Va con !important para no depender del orden en que Tailwind emita
       'hidden' y 'print:block'. */
    #print-receipt { display: block !important; }
}`;

/** Mide el comprobante oculto con el ancho útil del rollo y lo pasa a mm. */
const measureReceiptMm = (): number | null => {
    const el = document.getElementById('print-receipt');

    if (!el) {
        return null;
    }

    const previous = el.getAttribute('style');
    el.setAttribute(
        'style',
        `display:block;position:absolute;left:-10000px;top:0;visibility:hidden;` +
            `width:${PAPER_WIDTH_MM - PAGE_MARGIN_MM * 2}mm;max-width:none;` +
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

    return Math.ceil(heightPx * PX_TO_MM + PAGE_MARGIN_MM * 2 + SLACK_MM);
};

export function useThermalPrint(styleId: string) {
    const setCss = (heightMm: number | null) => {
        let style = document.getElementById(styleId);

        if (!style) {
            style = document.createElement('style');
            style.id = styleId;
            document.head.appendChild(style);
        }

        style.textContent = baseCss(heightMm);
    };

    // Ctrl+P también pasa por aquí, no solo el botón Imprimir.
    const onBeforePrint = () => setCss(measureReceiptMm());

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

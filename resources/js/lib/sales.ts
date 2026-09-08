/**
 * Helpers compartidos por las pantallas de ventas (listado, alta y recibo).
 */

export const PAYMENT_METHOD_LABELS: Record<string, string> = {
    efectivo: 'Efectivo',
    qr: 'QR',
    transferencia: 'Transferencia',
    tarjeta: 'Tarjeta',
};

/** El correlativo se guarda como entero y se muestra con relleno: 12 → 000012 */
export const receiptNumber = (n: number | string) => String(n).padStart(6, '0');

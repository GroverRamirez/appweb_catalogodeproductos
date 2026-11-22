<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Support\Facades\Storage;

class ReceiptPdf
{
    /**
     * Genera el PDF del comprobante de venta.
     */
    public static function make(Sale $sale): DomPdfInstance
    {
        $sale->loadMissing([
            'items',
            'client',
        ]);

        $store = [
            'nombre' => (string) Setting::get('store_name', config('app.name')),
            'direccion' => (string) Setting::get('address', ''),
            'telefono' => (string) Setting::get('whatsapp_number', ''),
            'email' => (string) Setting::get('email_contact', ''),
            'moneda' => (string) Setting::get('currency_symbol', 'Bs'),
        ];

        $symbol = $store['moneda'];

        $data = [
            'sale' => $sale,
            'tienda' => $store,
            'logo' => static::logoDataUri(),
            'moneda' => fn (float|int|string $val) => $symbol.' '.number_format((float) $val, 2, ',', '.'),
        ];

        return Pdf::loadView('receipts.sale-pdf', $data)->setPaper('letter');
    }

    /**
     * Nombre de archivo estándar para la descarga del comprobante.
     */
    public static function filename(Sale $sale): string
    {
        $number = str_pad((string) $sale->numero_recibo, 6, '0', STR_PAD_LEFT);

        return "comprobante-venta-{$number}.pdf";
    }

    /**
     * Logo incrustado como data URI para DomPDF.
     */
    private static function logoDataUri(): ?string
    {
        $path = Setting::get('logo_path');

        if (! $path || str_starts_with((string) $path, 'http')) {
            return null;
        }

        try {
            if (! Storage::exists((string) $path)) {
                return null;
            }

            return 'data:'.Storage::mimeType((string) $path)
                .';base64,'.base64_encode(Storage::get((string) $path));
        } catch (\Throwable) {
            return null;
        }
    }
}

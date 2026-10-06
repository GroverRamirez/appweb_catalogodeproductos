<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SalesReport;
use App\Support\CsvDownload;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes gerenciales: panel en pantalla y las tres exportaciones (CSV, PDF y
 * Excel). Todas las salidas se arman con `SalesReport`, de modo que el PDF que
 * se imprime dice exactamente lo mismo que la pantalla desde la que se pidio.
 */
class ReportController extends Controller
{
    public function index(Request $request): \Inertia\Response
    {
        $report = SalesReport::fromRequest($request);

        return Inertia::render('admin/reports/Index', [
            'range' => $report->range(),
            'kpis' => $report->kpis(),
            'series' => $report->series(),
            'topProducts' => $report->topRequested(),
            'topSold' => $report->topSold(),
            'topViewed' => $report->topViewed(),
            // Alimentan los graficos del panel: embudo de consultas por estado
            // e ingresos por categoria de producto.
            'statusBreakdown' => $report->statusBreakdown(),
            'byCategory' => $report->revenueByCategory(),
        ]);
    }

    /**
     * CSV de la serie diaria, en streaming: el archivo se arma fila por fila,
     * sin cargar el rango entero en memoria.
     */
    public function export(Request $request): StreamedResponse
    {
        $report = SalesReport::fromRequest($request);

        $rows = function () use ($report) {
            foreach ($report->series() as $row) {
                yield [
                    $row['day'],
                    $row['total'],
                    $row['sold'],
                    $row['sales'],
                    number_format($row['revenue'], 2, '.', ''),
                ];
            }
        };

        return CsvDownload::stream(
            $report->filename('csv'),
            ['Fecha', 'Consultas', 'Consultas vendidas', 'Ventas', 'Ingresos'],
            $rows(),
        );
    }

    /**
     * Reporte en PDF con encabezado institucional, filtros de fecha aplicados y
     * fila de totales.
     *
     * Con `?inline=1` se abre en el navegador en vez de descargarse, que es lo
     * comodo para mostrarlo en clase; por defecto descarga el archivo.
     */
    public function pdf(Request $request): Response
    {
        $report = SalesReport::fromRequest($request);

        $pdf = Pdf::loadView('reports.pdf', $this->documentData($report, $request))
            // Carta es el tamano que usan las impresoras del laboratorio;
            // cambiar a 'a4' si el papel institucional es A4.
            ->setPaper('letter');

        return $request->boolean('inline')
            ? $pdf->stream($report->filename('pdf'))
            : $pdf->download($report->filename('pdf'));
    }

    /**
     * Libro Excel con una hoja por bloque del reporte.
     */
    public function excel(Request $request): BinaryFileResponse
    {
        $report = SalesReport::fromRequest($request);

        return Excel::download(
            new ReportExport($report, $this->storeHeader()),
            $report->filename('xlsx'),
        );
    }

    /**
     * Datos que comparten el PDF y el Excel: cabecera institucional, rango y
     * los bloques del reporte.
     *
     * @return array<string, mixed>
     */
    private function documentData(SalesReport $report, Request $request): array
    {
        $store = $this->storeHeader();
        $symbol = $store['moneda'];

        return [
            'titulo' => 'Reporte gerencial',
            'tienda' => $store,
            'logo' => $this->logoDataUri(),
            'rango' => $report->label(),
            'emitidoEl' => now()->format('d/m/Y H:i'),
            'emitidoPor' => $request->user()?->name,
            'kpis' => $report->kpis(),
            'serie' => $report->series(),
            'totales' => $report->seriesTotals(),
            'masVendidos' => $report->topSold(),
            'masSolicitados' => $report->topRequested(),
            'categorias' => $report->revenueByCategory(),
            // Formateador unico para todo el documento: el simbolo sale de la
            // configuracion de la tienda, no esta escrito en la plantilla.
            'moneda' => fn (float|int|string $valor) => $symbol.' '.number_format((float) $valor, 2, ',', '.'),
        ];
    }

    /**
     * Identidad institucional para la cabecera de los documentos.
     *
     * @return array<string, string>
     */
    private function storeHeader(): array
    {
        return [
            'nombre' => (string) Setting::get('store_name', config('app.name')),
            'direccion' => (string) Setting::get('address', ''),
            'telefono' => (string) Setting::get('whatsapp_number', ''),
            'email' => (string) Setting::get('email_contact', ''),
            'moneda' => (string) Setting::get('currency_symbol', 'S/'),
        ];
    }

    /**
     * Logo incrustado como data URI.
     *
     * DomPDF no descarga imagenes remotas (`isRemoteEnabled` viene apagado a
     * proposito: habilitarlo convierte la plantilla en un lector de URLs
     * arbitrarias), asi que el logo se lee del disco y viaja dentro del PDF.
     * Si falta o falla la lectura, el documento se emite sin logo: un reporte
     * sin imagen sirve, uno que revienta al imprimirse no.
     */
    private function logoDataUri(): ?string
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

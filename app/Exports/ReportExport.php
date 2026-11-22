<?php

namespace App\Exports;

use App\Exports\Sheets\CategoriesSheet;
use App\Exports\Sheets\DailySeriesSheet;
use App\Exports\Sheets\ProductsSheet;
use App\Exports\Sheets\SummarySheet;
use App\Services\SalesReport;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Libro Excel del reporte gerencial.
 *
 * Una hoja por pregunta: el resumen se lee de un vistazo y las otras cuatro
 * quedan listas para tabla dinamica. Todas salen del mismo `SalesReport` que
 * alimenta la pantalla y el PDF, asi que los numeros no pueden separarse.
 *
 * El `Export` no es decorativo: en Laravel Excel 4 `Excel::download()` exige esa
 * interfaz, y `WithMultipleSheets` por si sola no la implica (las hojas la
 * heredan de `FromArray`, el libro que las agrupa no).
 */
class ReportExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, string>  $store  datos institucionales de la cabecera
     */
    public function __construct(
        private readonly SalesReport $report,
        private readonly array $store,
    ) {}

    /** @return array<int, object> */
    public function sheets(): array
    {
        return [
            new SummarySheet($this->report, $this->store),
            new DailySeriesSheet($this->report),
            new ProductsSheet(
                'Mas vendidos',
                ['Producto', 'Codigo', 'Cantidad', 'Ingresos'],
                $this->report->topSold(),
            ),
            new ProductsSheet(
                'Mas solicitados',
                ['Producto', 'Codigo', 'Cantidad pedida', 'Valor solicitado'],
                $this->report->topRequested(),
            ),
            new CategoriesSheet($this->report),
        ];
    }
}

<?php

namespace App\Exports\Sheets;

use App\Services\SalesReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Serie diaria con fila de totales al cierre.
 *
 * Los ingresos van como numero, no como texto con simbolo: una planilla que
 * llega con "Bs 1.200,00" en la celda no suma, y el usuario termina
 * retecleando el reporte.
 */
class DailySeriesSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly SalesReport $report) {}

    public function title(): string
    {
        return 'Detalle diario';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Fecha', 'Consultas', 'Consultas vendidas', 'Ventas', 'Ingresos'];
    }

    /** @return array<int, array<int, string|int|float>> */
    public function array(): array
    {
        $rows = array_map(fn (array $row) => [
            $row['day'],
            $row['total'],
            $row['sold'],
            $row['sales'],
            $row['revenue'],
        ], $this->report->series());

        $totals = $this->report->seriesTotals();

        $rows[] = [
            'Totales',
            $totals['inquiries'],
            $totals['sold'],
            $totals['sales'],
            $totals['revenue'],
        ];

        return $rows;
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        // La fila de totales es la ultima con datos; +1 por la cabecera.
        $lastRow = count($this->report->series()) + 2;

        return [
            1 => ['font' => ['bold' => true]],
            $lastRow => ['font' => ['bold' => true]],
        ];
    }
}

<?php

namespace App\Exports\Sheets;

use App\Services\SalesReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja de portada: encabezado institucional e indicadores del periodo.
 */
class SummarySheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  array<string, string>  $store
     */
    public function __construct(
        private readonly SalesReport $report,
        private readonly array $store,
    ) {}

    public function title(): string
    {
        return 'Resumen';
    }

    /** @return array<int, array<int, string|int|float>> */
    public function array(): array
    {
        $kpis = $this->report->kpis();

        return [
            [$this->store['nombre'], ''],
            [$this->store['direccion'], ''],
            [trim($this->store['telefono'].' '.$this->store['email']), ''],
            ['', ''],
            ['Reporte gerencial', ''],
            [$this->report->label(), ''],
            ['Emitido el', now()->format('d/m/Y H:i')],
            ['', ''],
            ['Indicador', 'Valor'],
            ['Consultas totales', $kpis['total_inquiries']],
            ['Consultas vendidas', $kpis['sold']],
            ['Consultas pendientes', $kpis['pending']],
            ['Consultas cerradas', $kpis['closed']],
            ['Tasa de conversion (%)', $kpis['conversion_rate']],
            ['Ventas confirmadas', $kpis['sales_count']],
            ['Ingresos', $kpis['revenue']],
            ['Ticket promedio', $kpis['average_ticket']],
            ['Margen', $kpis['margin']],
            ['Lineas vendidas sin costo (fuera del margen)', $kpis['lines_without_cost']],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            5 => ['font' => ['bold' => true, 'size' => 12]],
            9 => ['font' => ['bold' => true]],
        ];
    }
}

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
 * Ingresos por categoria, con la participacion de cada una sobre el total del
 * rango.
 */
class CategoriesSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private readonly SalesReport $report) {}

    public function title(): string
    {
        return 'Por categoria';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Categoria', 'Unidades', 'Ingresos', 'Participacion (%)'];
    }

    /** @return array<int, array<int, string|int|float>> */
    public function array(): array
    {
        $categories = $this->report->revenueByCategory();
        $total = array_sum(array_column($categories, 'revenue'));

        $rows = array_map(fn (array $c) => [
            $c['category'],
            $c['units'],
            $c['revenue'],
            $total > 0 ? round($c['revenue'] / $total * 100, 1) : 0,
        ], $categories);

        if ($rows === []) {
            return $rows;
        }

        $rows[] = [
            'Totales',
            array_sum(array_column($categories, 'units')),
            $total,
            100,
        ];

        return $rows;
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $styles = [1 => ['font' => ['bold' => true]]];
        $rows = count($this->report->revenueByCategory());

        if ($rows > 0) {
            $styles[$rows + 2] = ['font' => ['bold' => true]];
        }

        return $styles;
    }
}

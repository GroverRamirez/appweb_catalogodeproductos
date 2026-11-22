<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ranking de productos. La misma hoja sirve para los mas vendidos y para los
 * mas solicitados: cambian el titulo y las cabeceras, no la estructura.
 */
class ProductsSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array{product_id: int|null, name: string, code: string, qty: int, revenue: float}>  $products
     */
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly array $products,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return $this->headings;
    }

    /** @return array<int, array<int, string|int|float>> */
    public function array(): array
    {
        $rows = array_map(fn (array $p) => [
            $p['name'],
            $p['code'],
            $p['qty'],
            $p['revenue'],
        ], $this->products);

        if ($rows === []) {
            return $rows;
        }

        $rows[] = [
            'Totales',
            '',
            array_sum(array_column($this->products, 'qty')),
            array_sum(array_column($this->products, 'revenue')),
        ];

        return $rows;
    }

    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $styles = [1 => ['font' => ['bold' => true]]];

        if ($this->products !== []) {
            $styles[count($this->products) + 2] = ['font' => ['bold' => true]];
        }

        return $styles;
    }
}

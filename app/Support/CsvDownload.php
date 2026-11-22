<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvDownload
{
    /**
     * Devuelve un StreamedResponse de CSV.
     *
     * @param  string  $filename  ej: 'consultas-2026-05.csv'
     * @param  array<int,string>  $headers  fila de cabecera
     * @param  iterable  $rows  filas (cada una array indexado)
     */
    public static function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para que Excel reconozca acentos
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::safeCell(...), $row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Neutraliza la inyeccion de formulas: Excel y LibreOffice ejecutan una
     * celda que empieza con = + - @ (o tab/retorno) como formula. Nombres y
     * mensajes llegan del formulario publico, asi que un `=HYPERLINK(...)`
     * escrito por un visitante se ejecutaria al abrir la exportacion. Se
     * antepone un apostrofo, que la planilla muestra como texto.
     *
     * Los numeros (o texto numerico, como un decimal de la base) pasan tal
     * cual: un total negativo no es formula.
     */
    public static function safeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}

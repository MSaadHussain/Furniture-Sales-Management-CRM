<?php

namespace App\Services\Export;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a tabular dataset as CSV or XLSX. Rows are an iterable of flat arrays
 * (one array of scalar cell values per row), so callers can pass a generator
 * backed by a lazy query cursor.
 */
class TabularExport
{
    /**
     * @param array<int,string> $headers
     * @param iterable<array<int,mixed>> $rows
     */
    public static function download(string $baseName, array $headers, iterable $rows, string $format = 'csv'): StreamedResponse
    {
        return strtolower($format) === 'xlsx'
            ? self::xlsx($baseName, $headers, $rows)
            : self::csv($baseName, $headers, $rows);
    }

    private static function csv(string $baseName, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens accented characters correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
        }, "{$baseName}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function xlsx(string $baseName, array $headers, iterable $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray($headers, null, 'A1');

        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray(array_values($row), null, 'A' . $r);
            $r++;
        }

        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, "{$baseName}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

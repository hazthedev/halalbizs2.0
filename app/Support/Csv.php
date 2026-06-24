<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a CSV download (M1.5 exports). RFC-4180 quoting, no BOM games. */
class Csv
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, string|int|null>>  $rows
     */
    public static function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, ',', '"', '');

            foreach ($rows as $row) {
                fputcsv($out, $row, ',', '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

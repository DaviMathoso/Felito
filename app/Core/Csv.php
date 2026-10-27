<?php
declare(strict_types=1);

namespace App\Core;

final class Csv
{
    /** Lê CSV (separador ; ou ,) e devolve as linhas já com os cabeçalhos em minúsculas na primeira posição. */
    public static function parse(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $firstLine = strtok($text, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $text);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($row === [null] || count(array_filter($row, static fn($cell) => trim((string) $cell) !== '')) === 0) continue;
            $rows[] = array_map(static fn($cell) => trim((string) $cell), $row);
        }
        fclose($handle);
        if ($rows) $rows[0] = array_map(static fn($cell) => mb_strtolower($cell), $rows[0]);
        return $rows;
    }

    /** Envia o arquivo CSV ao navegador (UTF-8 com BOM, separador ;, compatível com Excel). */
    public static function download(string $filename, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) fputcsv($out, $row, ';', '"', '', "\n");
        fclose($out);
        exit;
    }
}

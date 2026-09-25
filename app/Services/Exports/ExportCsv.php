<?php

namespace App\Services\Exports;

/**
 * An export as a CSV a spreadsheet opens (UTF-8 with a byte-order mark, so Arabic survives). A list
 * export is headings + every matching row, nothing above them, so it imports cleanly; a details
 * export is label / value pairs.
 */
class ExportCsv
{
    /** @param  resource  $out */
    public function write($out, ExportDocument $document): void
    {
        fwrite($out, "\xEF\xBB\xBF");

        if ($document->layout === ExportDocument::TABLE) {
            fputcsv($out, array_map($this->cell(...), $document->headings));

            foreach ($document->rows() as $row) {
                fputcsv($out, array_map($this->cell(...), $row));
            }

            return;
        }

        fputcsv($out, array_map($this->cell(...), array_filter([$document->title, $document->subtitle])));

        foreach ($document->rows() as $row) {
            fputcsv($out, isset($row['heading']) ? [$this->cell($row['heading'])] : [$this->cell($row['label']), $this->cell($row['value'])]);
        }
    }

    /** Neutralise values a spreadsheet would run as a formula ("=HYPERLINK(...)"), leaving negative numbers alone. */
    private function cell(string $value): string
    {
        return preg_match('/^(?:[=+@\t\r]|-(?!\d))/', $value) ? "'".$value : $value;
    }
}

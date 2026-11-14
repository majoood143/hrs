<?php

namespace Packstub\FormBuilder\Filament;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The CSV's rows as an Excel sheet (OpenSpout, which filament/actions already requires).
 */
class SubmissionsXlsx
{
    /**
     * @param  Builder<FormSubmission>|Collection<int, FormSubmission>  $submissions
     */
    public static function download(Form $form, Builder|Collection $submissions): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'fb-xlsx');
        static::write($form, $submissions, $path);

        return response()
            ->download($path, Str::slug($form->slug.'-submissions-'.now()->format('Y-m-d')).'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * @param  Builder<FormSubmission>|Collection<int, FormSubmission>  $submissions
     */
    public static function write(Form $form, Builder|Collection $submissions, string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        foreach (SubmissionsCsv::rows($form, $submissions) as $row) {
            // Visitor input: never let a leading "=" become a formula (Cell::fromValue would).
            $writer->addRow(new Row(array_map(
                fn (string|int|null $value): Cell => is_string($value) ? new Cell\StringCell($value, null) : Cell::fromValue($value),
                $row,
            )));
        }

        $writer->close();
    }
}

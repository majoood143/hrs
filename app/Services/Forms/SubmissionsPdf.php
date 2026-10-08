<?php

namespace App\Services\Forms;

use App\Services\Exports\ExportDocument;
use App\Services\Exports\ExportPdf;
use App\Support\Locale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A form's submissions as a PDF, in one language: each submission is a block of question / answer
 * rows (a form can have far more fields than a table has room for), through the same template and
 * logo as every other admin export. One submission gets its own title and details; a list stops at
 * MAX_SUBMISSIONS and says so (the CSV has them all).
 */
class SubmissionsPdf
{
    public const MAX_SUBMISSIONS = 300;

    public function __construct(private readonly ExportPdf $pdf) {}

    /**
     * @param  Builder<FormSubmission>|Collection<int, FormSubmission>  $submissions
     */
    public function download(Form $form, Builder|Collection $submissions, string $locale): StreamedResponse
    {
        $single = $submissions instanceof Collection && $submissions->count() === 1;
        $filename = $single
            ? Str::slug($form->slug.'-submission-'.$submissions->first()->getKey()).'-'.$locale.'.pdf'
            : Str::slug($form->slug.'-submissions-'.now()->format('Y-m-d')).'-'.$locale.'.pdf';

        return response()->streamDownload(function () use ($form, $submissions, $locale): void {
            echo Locale::within($locale, fn (): string => $this->pdf->render($this->document($form, $submissions)));
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Built in the current language (call it inside Locale::within).
     *
     * @param  Builder<FormSubmission>|Collection<int, FormSubmission>  $submissions
     */
    public function document(Form $form, Builder|Collection $submissions): ExportDocument
    {
        // a fresh copy: the form keeps its fields once resolved, in the language they were read in
        $form = $form->newQuery()->find($form->getKey()) ?? $form;
        $formName = (string) $form->name;

        if ($submissions instanceof Builder) {
            $total = (clone $submissions)->count();
            $records = $submissions->limit(self::MAX_SUBMISSIONS)->get();
        } else {
            $total = $submissions->count();
            $records = $submissions->take(self::MAX_SUBMISSIONS)->values();
        }

        $records->each(fn (FormSubmission $record) => $record->setRelation('form', $form));

        if ($total === 1 && $records->count() === 1) {
            /** @var FormSubmission $record */
            $record = $records->first();
            $rows = $this->answers($form, $record);
            $facts = array_filter([
                __('admin_form_submissions.pdf.submitted_at') => $record->created_at?->format('Y-m-d H:i'),
                __('admin_form_submissions.pdf.page') => $record->source_url,
            ]);

            return new ExportDocument(
                ExportDocument::DETAILS,
                __('admin_form_submissions.pdf.one_title', ['id' => $record->getKey()]),
                $formName,
                $facts,
                [],
                fn () => $rows,
                count($rows),
            );
        }

        $rows = [];

        foreach ($records as $record) {
            $rows[] = ['heading' => __('admin_form_submissions.pdf.one_title', ['id' => $record->getKey()])
                .' · '.$record->created_at?->format('Y-m-d H:i')];
            array_push($rows, ...$this->answers($form, $record));
        }

        $facts = [
            __('admin_export.generated') => now()->format('Y-m-d H:i'),
            __('admin_export.records') => $total > $records->count()
                ? __('admin_form_submissions.pdf.showing', ['shown' => $records->count(), 'total' => $total])
                : (string) $total,
        ];

        return new ExportDocument(
            ExportDocument::DETAILS,
            __('admin_form_submissions.pdf.title', ['form' => $formName]),
            null,
            $facts,
            [],
            fn () => $rows,
            count($rows),
        );
    }

    /**
     * The submission's answers as label / value rows: labels from the form as it is now (in the
     * PDF's language), else as they were when it was sent; files by their name, not a link.
     *
     * @return list<array{label: string, value: string}>
     */
    private function answers(Form $form, FormSubmission $record): array
    {
        $rows = [];

        foreach ($record->formatted() as $key => $row) {
            $field = $form->field($key);
            $value = $row['value'];

            if ($value !== '' && $field?->type::id() === 'file') {
                $value = basename((string) $record->value($key));
            }

            $rows[] = [
                'label' => $field?->label ?? $row['label'],
                'value' => $value === '' ? '—' : $value,
            ];
        }

        return $rows;
    }
}

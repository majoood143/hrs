<?php

namespace App\Filament\Forms;

use App\Filament\Support\TranslatableInput;
use App\Services\Forms\SubmissionsPdf;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "PDF (English)" / "PDF (Arabic)" on a form's submissions page (through
 * `FormBuilder::registerSubmissionActions()`, which puts them in the page's own menus): the
 * submissions as filtered and sorted (Export menu), the selected ones (bulk menu) and one (row menu).
 */
class SubmissionPdfActions
{
    /** @return array{export: list<Action>, record: list<Action>, bulk: list<BulkAction>} */
    public static function for(Form $form, HasTable $page): array
    {
        $export = $record = $bulk = [];

        foreach (TranslatableInput::locales() as $locale => $meta) {
            $label = __('admin_form_submissions.pdf.in', [
                'language' => Lang::has("admin_export.languages.{$locale}") ? __("admin_export.languages.{$locale}") : $meta['name'],
            ]);
            $suffix = Str::studly($locale);

            $export[] = Action::make('exportPdf'.$suffix)
                ->label($label)
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn (): StreamedResponse => app(SubmissionsPdf::class)->download($form, $page->getFilteredSortedTableQuery(), $locale));

            $record[] = Action::make('submissionPdf'.$suffix)
                ->label($label)
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn (FormSubmission $record): StreamedResponse => app(SubmissionsPdf::class)->download($form, new Collection([$record]), $locale));

            $bulk[] = BulkAction::make('selectedPdf'.$suffix)
                ->label($label)
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn (Collection $records): StreamedResponse => app(SubmissionsPdf::class)->download($form, $records->sortByDesc('created_at')->values(), $locale));
        }

        return ['export' => $export, 'record' => $record, 'bulk' => $bulk];
    }
}

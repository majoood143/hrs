<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\Concerns;

use App\Filament\Support\TranslatableInput;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Packstub\FormBuilder\Support\FormPreview;

/**
 * "Preview" on the form editor (create and edit): the form as it stands in the editor,
 * unsaved changes included, in a slide-over with a tab per language.
 */
trait HasPreviewAction
{
    protected function previewAction(): Action
    {
        return Action::make('preview')
            ->label(__('packstub-form-builder::form-builder.preview.action'))
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->slideOver()
            ->modalWidth(Width::FiveExtraLarge)
            ->modalHeading(__('packstub-form-builder::form-builder.preview.heading'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('packstub-form-builder::form-builder.preview.close'))
            ->modalContent(function (): View {
                $token = FormPreview::store((array) $this->data, auth()->user());

                $urls = collect(TranslatableInput::locales())
                    ->mapWithKeys(fn (array $meta, string $code): array => [$code => FormPreview::url($token, $code)])
                    ->all();

                return view('packstub-form-builder::preview.modal', [
                    'urls' => $urls,
                    'locale' => array_key_exists(app()->getLocale(), $urls) ? app()->getLocale() : array_key_first($urls),
                ]);
            });
    }
}

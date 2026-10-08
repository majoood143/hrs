<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Filament\Resources\FormResource\Concerns\HasPreviewAction;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Support\FormTemplates;

class CreateForm extends CreateRecord
{
    use HasPreviewAction;

    protected function getHeaderActions(): array
    {
        return [$this->templateAction(), $this->previewAction()];
    }

    /**
     * "Start from a template": replaces the fields with a ready-made set, and names the form
     * after the template if it has no name yet. Only on a new form: on an existing one it
     * would orphan the answers already received.
     */
    protected function templateAction(): Action
    {
        return Action::make('template')
            ->label(__('packstub-form-builder::form-builder.templates.action'))
            ->icon('heroicon-o-squares-plus')
            ->color('gray')
            ->modalHeading(__('packstub-form-builder::form-builder.templates.heading'))
            ->modalDescription(fn (): ?string => filled($this->data['fields'] ?? null)
                ? __('packstub-form-builder::form-builder.templates.replaces')
                : null)
            ->modalSubmitActionLabel(__('packstub-form-builder::form-builder.templates.use'))
            ->schema([
                Radio::make('template')
                    ->label(__('packstub-form-builder::form-builder.templates.choose'))
                    ->hiddenLabel()
                    ->options(FormTemplates::options())
                    ->descriptions(FormTemplates::descriptions())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $state = $this->form->getRawState();
                $state['fields'] = FormTemplates::fields((string) $data['template']);

                if (blank($state['name']['en'] ?? null)) {
                    $state['name'] = FormTemplates::name((string) $data['template']);
                    $state['slug'] = filled($state['slug'] ?? null) ? $state['slug'] : Str::slug($state['name']['en'] ?? '');
                }

                $this->form->fill($state);
            });
    }

    public static function getResource(): string
    {
        return FormBuilderPlugin::get()->getResource();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}

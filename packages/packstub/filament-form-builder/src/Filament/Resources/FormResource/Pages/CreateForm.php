<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;
use Packstub\FormBuilder\Filament\Resources\FormResource\Concerns\HasPreviewAction;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Support\FormTemplates;

class CreateForm extends CreateRecord
{
    use HasPreviewAction;

    protected function getHeaderActions(): array
    {
        return [$this->templateAction(), ...app(FormBuilder::class)->createActions(), $this->previewAction()];
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
            ->action(fn (array $data) => $this->startWith(
                FormTemplates::fields((string) $data['template']),
                FormTemplates::name((string) $data['template']),
            ));
    }

    /**
     * Replace the fields with a ready-made set; the name (and the slug made from it) and any other
     * attribute given (`closes_at`, `description`...) are filled only where nothing is typed yet.
     *
     * @param  array<int, array{type: string, data: array<string, mixed>}>  $fields
     * @param  array<string, string>  $name  per language
     * @param  array<string, mixed>  $attributes
     */
    public function startWith(array $fields, array $name, array $attributes = []): void
    {
        $state = $this->form->getRawState();
        $state['fields'] = $fields;

        if (blank($state['name']['en'] ?? null)) {
            $state['name'] = $name;
            $state['slug'] = filled($state['slug'] ?? null) ? $state['slug'] : Str::slug($state['name']['en'] ?? '');
        }

        foreach ($attributes as $key => $value) {
            if (blank($state[$key] ?? null)) {
                $state[$key] = $value;
            }
        }

        $this->form->fill($state);
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

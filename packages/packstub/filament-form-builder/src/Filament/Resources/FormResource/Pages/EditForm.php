<?php

namespace Packstub\FormBuilder\Filament\Resources\FormResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Packstub\FormBuilder\Filament\Resources\FormResource\Concerns\HasPreviewAction;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Models\Form;

class EditForm extends EditRecord
{
    use HasPreviewAction;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    public static function getResource(): string
    {
        return FormBuilderPlugin::get()->getResource();
    }

    /** The form's editor tab (next to its submissions). */
    public static function getNavigationLabel(): string
    {
        return __('packstub-form-builder::form-builder.pages.edit');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            Action::make('open')
                ->label(__('packstub-form-builder::form-builder.actions.open_page'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): ?string => $this->getRecord()->pageUrl(), shouldOpenInNewTab: true)
                ->visible(fn (): bool => $this->getRecord() instanceof Form && $this->getRecord()->pageUrl() !== null),
            ...app(FormBuilder::class)->recordActions(),
            DeleteAction::make(),
        ];
    }
}

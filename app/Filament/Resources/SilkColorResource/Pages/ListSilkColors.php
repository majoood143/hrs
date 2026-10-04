<?php

namespace App\Filament\Resources\SilkColorResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\SilkColorResource;
use App\Support\Silks\SilksDefaults;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSilkColors extends ListRecords
{
    use HasExportActions;

    protected static string $resource = SilkColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('restoreDefaults')
                ->label(__('admin_silks.actions.restore_defaults'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription(__('admin_silks.actions.restore_defaults_help'))
                ->visible(fn () => SilkColorResource::canCreate())
                ->action(function () {
                    $added = SilksDefaults::addMissingColors();

                    Notification::make()
                        ->title(trans_choice('admin_silks.actions.restored', $added, ['count' => $added]))
                        ->success()
                        ->send();
                }),
        ];
    }
}

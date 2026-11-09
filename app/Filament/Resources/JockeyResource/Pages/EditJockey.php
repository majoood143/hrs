<?php

namespace App\Filament\Resources\JockeyResource\Pages;

use App\Filament\Resources\JockeyResource;
use App\Filament\Support\PendingPosts;
use App\Models\Jockey;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditJockey extends EditRecord
{
    protected static string $resource = JockeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // the form holds its own copy of the status: without the refresh, Save would put the old one back
            PendingPosts::approveAction()->after(fn () => $this->refreshFormData(['status'])),
            Action::make('toggleStatus')
                ->label(fn (Jockey $record): string => __($record->status === 'active' ? 'jockeys.deactivate' : 'jockeys.activate'))
                ->icon(fn (Jockey $record): string => $record->status === 'active' ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                ->color(fn (Jockey $record): string => $record->status === 'active' ? 'gray' : 'success')
                ->visible(fn (Jockey $record): bool => $record->status !== PendingPosts::STATUS)
                ->action(function (Jockey $record): void {
                    $record->update(['status' => $record->status === 'active' ? 'inactive' : 'active']);
                    $this->refreshFormData(['status']);

                    Notification::make()->success()->title(__('jockeys.status_now', ['status' => Jockey::statusOptions()[$record->status]]))->send();
                }),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use Exception;
use Filament\Schemas\Components\Tabs\Tab;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

use App\Mail\WelcomeEmail;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class ListUsers extends ListRecords
{
    use HasExportActions;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('sendWelcomeEmail')
                ->label('Send Welcome Email')
                ->action(function (SettingsService $settings) {
                    $users = $this->getTable()->getRecords();

                    foreach ($users as $user) {
                        try {
                            Mail::to($user->email)->send(new WelcomeEmail($user));

                            Notification::make()
                                ->title("Welcome email sent to {$user->email}")
                                ->success()
                                ->send();
                        } catch (Exception $e) {
                            Notification::make()
                                ->title("Failed to send email to {$user->email}: {$e->getMessage()}")
                                ->danger()
                                ->send();
                        }
                    }
                })
                ->requiresConfirmation()
        ];
    }

    public function getTabs(): array
    {
        return [
            null => Tab::make('All'),
            'Admins' => Tab::make()->query(fn ($query) => $query->where('is_admin', true)),
            'Stable owner' => Tab::make()->query(fn($query) => $query->where('type', 'stable_owner')),
            'Owner' => Tab::make()->query(fn($query) => $query->where('type', 'owner')),
            'Trainer' => Tab::make()->query(fn($query) => $query->where('type', 'trainer')),
            'Veterinarian' => Tab::make()->query(fn($query) => $query->where('type', 'veterinarian')),
            'Jockey' => Tab::make()->query(fn($query) => $query->where('type', 'jockey')),
            'Groom' => Tab::make()->query(fn($query) => $query->where('type', 'groom')),
            'Farrier' => Tab::make()->query(fn($query) => $query->where('type', 'farrier')),
            'Trainer assistant' => Tab::make()->query(fn($query) => $query->where('type', 'trainer_assistant')),
        ];
    }
}

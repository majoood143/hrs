<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\UserResource;
use App\Mail\WelcomeEmail;
use App\Services\SettingsService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
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
                ->label(__('admin_user.actions.send_welcome'))
                ->icon('heroicon-o-envelope')
                ->modalDescription(__('admin_user.actions.send_welcome_description'))
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
                ->requiresConfirmation(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make(__('admin_user.tabs.all')),
            // "staff" = anyone with a Shield role, i.e. who can actually do something in /admin
            'staff' => Tab::make(__('admin_user.tabs.staff'))
                ->icon('heroicon-o-shield-check')
                ->query(fn (Builder $query) => $query->whereHas('roles')),
        ];

        foreach (UserResource::typeOptions() as $type => $label) {
            $tabs[$type] = Tab::make($label)->query(fn (Builder $query) => $query->where('type', $type));
        }

        return $tabs;
    }
}

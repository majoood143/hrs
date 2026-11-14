<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\MobileDevice;
use Filament\Actions\Action;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** One profile: who they are (the app's optional profile), their phones, and below, what they did in the app. */
class ViewCustomer extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = CustomerResource::class;

    public const REVEAL_PERMISSION = 'RevealNationalId:Customer';

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin_crm.profile.sections.profile'))
                ->icon('heroicon-o-identification')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('phone')->label(__('admin_customer.columns.phone'))->formatStateUsing(fn (string $state) => "\u{2066}+{$state}\u{2069}")->copyable(fn (Customer $record) => '+'.$record->phone),
                    TextEntry::make('name')->label(__('admin_customer.columns.name'))->placeholder('—'),
                    TextEntry::make('email')->label(__('admin_customer.columns.email'))->placeholder('—'),
                    TextEntry::make('country')->label(__('admin_crm.fields.country'))->placeholder('—')->badge()->color('gray'),
                    TextEntry::make('gender')->label(__('admin_crm.fields.gender'))->formatStateUsing(fn (string $state) => __('admin_crm.genders.'.$state))->placeholder('—'),
                    TextEntry::make('date_of_birth')
                        ->label(__('admin_crm.fields.date_of_birth'))
                        ->date('M d, Y')
                        ->placeholder('—')
                        ->helperText(fn (Customer $record) => $record->date_of_birth ? __('admin_crm.profile.age', ['age' => $record->date_of_birth->age]) : null),
                    // masked here and in exports; the full number only through the logged action above
                    TextEntry::make('national_id')
                        ->label(__('admin_crm.fields.national_id'))
                        // isolated left-to-right: "••••5678" in the Arabic admin too
                        ->getStateUsing(fn (Customer $record) => $record->maskedNationalId() ? "\u{2066}".$record->maskedNationalId()."\u{2069}" : null)
                        ->placeholder('—'),
                    IconEntry::make('marketing_opt_in')->label(__('admin_crm.fields.marketing'))->boolean(),
                    TextEntry::make('source')->label(__('admin_crm.fields.source'))->formatStateUsing(fn (string $state) => __('admin_crm.sources.'.$state))->badge(),
                    TextEntry::make('completion')->label(__('admin_crm.fields.completion'))->getStateUsing(fn (Customer $record) => $record->profileCompletion().'%'),
                    TextEntry::make('created_at')->label(__('admin_customer.columns.created_at'))->dateTime('M d, Y H:i'),
                    TextEntry::make('last_login_at')->label(__('admin_customer.columns.last_login'))->dateTime('M d, Y H:i')->placeholder('—'),
                ]),
            Section::make(__('admin_crm.profile.sections.activity'))
                ->icon('heroicon-o-chart-bar')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('events_30')
                        ->label(__('admin_crm.profile.events_30'))
                        ->getStateUsing(fn (Customer $record) => $record->events()->where('occurred_at', '>=', now()->subDays(30))->count()),
                    TextEntry::make('last_event')
                        ->label(__('admin_crm.fields.last_activity'))
                        ->getStateUsing(fn (Customer $record) => $record->events()->max('occurred_at'))
                        ->since()
                        ->placeholder('—'),
                    TextEntry::make('orders_count')
                        ->label(__('admin_customer.columns.orders'))
                        ->getStateUsing(fn (Customer $record) => $record->orders()->count()),
                    TextEntry::make('devices')
                        ->label(__('admin_crm.profile.devices'))
                        ->getStateUsing(fn (Customer $record) => MobileDevice::query()->customerSignedIn($record->getKey())->get()
                            ->map(fn (MobileDevice $device) => trim(($device->platform ?? '?').' '.($device->app_version ?? '')))
                            ->all())
                        ->badge()
                        ->placeholder(__('admin_crm.profile.no_devices')),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('revealNationalId')
                ->label(__('admin_crm.profile.reveal'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->visible(fn () => filled($this->getRecord()->national_id) && auth()->user()?->can(self::REVEAL_PERMISSION))
                ->requiresConfirmation()
                ->modalDescription(__('admin_crm.profile.reveal_confirm'))
                ->action(function (): void {
                    /** @var Customer $customer */
                    $customer = $this->getRecord();

                    activity('crm')
                        ->causedBy(auth()->user())
                        ->performedOn($customer)
                        ->event('revealed')
                        ->log('National ID revealed');

                    Notification::make()
                        ->title(__('admin_crm.profile.revealed', ['id' => $customer->national_id]))
                        ->persistent()
                        ->info()
                        ->send();
                }),
        ];
    }
}

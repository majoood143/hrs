<?php

namespace App\Filament\Resources\StableResource\RelationManagers;

use App\Models\StablePaymentAccount;
use App\Models\User;
use App\Services\Stables\StablePaymentAccounts;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * A stable's own gateway keys, waiting for (or past) an admin's review. Keys are only ever shown
 * masked; approving is what lets the stable take its bookings' payments itself.
 */
class PaymentAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentAccounts';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin_stable.payments.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    private function allowed(): bool
    {
        return Gate::allows('update', $this->getOwnerRecord());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('gateway')
            ->columns([
                TextColumn::make('gateway')
                    ->label(__('admin_stable.payments.gateway'))
                    ->formatStateUsing(fn (StablePaymentAccount $record) => $record->gateway->label())
                    ->description(fn (StablePaymentAccount $record) => $this->getOwnerRecord()->bookingSettings()->paymentGateway() === $record->gateway->value
                        && $this->getOwnerRecord()->bookingSettings()->paymentMode() === 'own'
                        ? __('admin_stable.payments.in_use') : null),
                IconColumn::make('test_mode')
                    ->label(__('admin_stable.payments.test_mode'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('admin_stable.payments.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('stable_panel.payments.badge.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        StablePaymentAccount::APPROVED => 'success',
                        StablePaymentAccount::REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('last_test_message')
                    ->label(__('admin_stable.payments.last_test'))
                    ->icon(fn (StablePaymentAccount $record) => $record->last_test_ok ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->iconColor(fn (StablePaymentAccount $record) => $record->last_test_ok ? 'success' : 'warning')
                    ->description(fn (StablePaymentAccount $record) => $record->last_tested_at?->diffForHumans())
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('reviewed_at')
                    ->label(__('admin_stable.payments.reviewed'))
                    ->dateTime()
                    ->description(fn (StablePaymentAccount $record) => $record->reviewer?->name)
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('keys')
                    ->label(__('admin_stable.payments.keys'))
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->schema(fn (StablePaymentAccount $record) => collect(StablePaymentAccount::FIELDS[$record->gateway->value] ?? [])
                        ->map(fn (bool $secret, string $key) => TextEntry::make($key)
                            ->label(__('stable_panel.payments.fields.'.$key))
                            ->state($record->masked($key)))
                        ->values()->all()),
                Action::make('test')
                    ->label(__('admin_stable.payments.test'))
                    ->icon('heroicon-o-signal')
                    ->color('gray')
                    ->visible(fn () => $this->allowed())
                    ->action(function (StablePaymentAccount $record): void {
                        [$ok, $message] = app(StablePaymentAccounts::class)->test($record);
                        Notification::make()->{$ok ? 'success' : 'warning'}()->title($message)->send();
                    }),
                Action::make('approve')
                    ->label(__('admin_stable.payments.approve'))
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (StablePaymentAccount $record) => $record->test_mode
                        ? __('admin_stable.payments.approve_test_mode')
                        : __('admin_stable.payments.approve_description'))
                    ->visible(fn (StablePaymentAccount $record) => $this->allowed() && ! $record->isApproved() && $record->isComplete())
                    ->action(function (StablePaymentAccount $record): void {
                        app(StablePaymentAccounts::class)->approve($record, auth()->user() instanceof User ? auth()->user() : null);
                        Notification::make()->success()->title(__('admin_stable.payments.approved'))->send();
                    }),
                Action::make('reject')
                    ->label(__('admin_stable.payments.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (StablePaymentAccount $record) => $this->allowed() && $record->status !== StablePaymentAccount::REJECTED)
                    ->schema([
                        Textarea::make('note')
                            ->label(__('admin_stable.approval.reason'))
                            ->helperText(__('admin_stable.approval.reason_hint'))
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (StablePaymentAccount $record, array $data): void {
                        app(StablePaymentAccounts::class)->reject($record, $data['note'], auth()->user() instanceof User ? auth()->user() : null);
                        Notification::make()->success()->title(__('admin_stable.payments.rejected'))->send();
                    }),
            ])
            ->emptyStateHeading(__('admin_stable.payments.empty'));
    }
}

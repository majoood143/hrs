<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationLogResource\Pages\ListNotificationLogs;
use App\Models\NotificationLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Every SMS we tried to send and what came of it: the answer to "did the customer get it?". */
class NotificationLogResource extends Resource
{
    protected static ?string $model = NotificationLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.payments');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_notification_log.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_notification_log.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_notification_log.navigation.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('order');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('admin_notification_log.columns.created_at'))
                    ->dateTime('M d, Y H:i:s')
                    ->sortable(),
                TextColumn::make('channel')
                    ->label(__('admin_notification_log.columns.channel'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('type')
                    ->label(__('admin_notification_log.columns.type'))
                    ->formatStateUsing(fn (string $state) => Str::of($state)->replace('_', ' ')->headline())
                    ->searchable(),
                TextColumn::make('recipient')
                    ->label(__('admin_notification_log.columns.recipient'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('status')
                    ->label(__('admin_notification_log.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin_notification_log.statuses.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success',
                        'skipped' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('order.order_number')
                    ->label(__('admin_notification_log.columns.order'))
                    ->placeholder('—')
                    ->searchable()
                    ->url(fn (NotificationLog $record) => $record->service_order_id
                        ? ServiceOrderResource::getUrl('view', ['record' => $record->service_order_id])
                        : null),
                TextColumn::make('error')
                    ->label(__('admin_notification_log.columns.error'))
                    ->placeholder('—')
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn (NotificationLog $record) => $record->error),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin_notification_log.columns.status'))
                    ->options(['sent' => __('admin_notification_log.statuses.sent'), 'failed' => __('admin_notification_log.statuses.failed'), 'skipped' => __('admin_notification_log.statuses.skipped')]),
                SelectFilter::make('channel')
                    ->label(__('admin_notification_log.columns.channel'))
                    ->options(['sms' => 'SMS', 'email' => 'Email']),
                SelectFilter::make('type')
                    ->label(__('admin_notification_log.columns.type'))
                    ->options(fn () => NotificationLog::query()->distinct()->pluck('type', 'type')->map(fn ($type) => Str::of($type)->replace('_', ' ')->headline()->toString())->all()),
            ])
            ->emptyStateHeading(__('admin_notification_log.empty'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotificationLogs::route('/'),
        ];
    }
}

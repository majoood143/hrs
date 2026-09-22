<?php

namespace App\Filament\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Filament\Resources\ServiceOrderResource\Pages\ListServiceOrders;
use App\Filament\Resources\ServiceOrderResource\Pages\ViewServiceOrder;
use App\Models\ServiceOrder;
use App\Models\SiteSetting;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Orders customers placed for a service, with their money breakdown and payment trail.
 * Read-only for now: creating and deleting an order is not something an admin does by hand.
 */
class ServiceOrderResource extends Resource
{
    protected static ?string $model = ServiceOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.payments');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_service_order.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_service_order.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_service_order.navigation.plural');
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
        return parent::getEloquentQuery()->with('service');
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state) => SiteSetting::formatCurrency($state, 3);

        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label(__('admin_service_order.fields.order_number'))
                    ->searchable()
                    ->copyable()
                    ->weight('medium'),
                TextColumn::make('service.name')
                    ->label(__('admin_service_order.fields.service'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label(__('admin_service_order.fields.customer_name'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->label(__('admin_service_order.fields.customer_phone'))
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('admin_service_order.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => $state->color()),
                TextColumn::make('payment_status')
                    ->label(__('admin_service_order.fields.payment_status'))
                    ->badge()
                    ->formatStateUsing(fn (PaymentStatus $state) => $state->label())
                    ->color(fn (PaymentStatus $state) => $state->color()),
                TextColumn::make('payment_method')
                    ->label(__('admin_service_order.fields.payment_method'))
                    ->formatStateUsing(fn (?PaymentGateway $state) => $state?->label())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('total')
                    ->label(__('admin_service_order.fields.total'))
                    ->formatStateUsing($money)
                    ->sortable(),
                TextColumn::make('fee_amount')
                    ->label(__('admin_service_order.fields.fee_amount'))
                    ->formatStateUsing($money)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('paid_at')
                    ->label(__('admin_service_order.fields.paid_at'))
                    ->dateTime('M d, Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin_service_order.fields.created_at'))
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin_service_order.fields.status'))
                    ->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])->all())
                    ->multiple(),
                SelectFilter::make('payment_status')
                    ->label(__('admin_service_order.fields.payment_status'))
                    ->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $s) => [$s->value => $s->label()])->all())
                    ->multiple(),
                SelectFilter::make('payment_method')
                    ->label(__('admin_service_order.fields.payment_method'))
                    ->options(collect(PaymentGateway::cases())->mapWithKeys(fn (PaymentGateway $g) => [$g->value => $g->label()])->all())
                    ->multiple(),
                Filter::make('needs_refund')
                    ->label(__('admin_service_order.filters.needs_refund'))
                    ->query(fn (Builder $query) => $query
                        ->where('payment_status', PaymentStatus::Paid->value)
                        ->whereIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('view_agent_details')
                        ->label(__('admin_service_order.actions.view_agent_details'))
                        ->icon('heroicon-o-device-phone-mobile')
                        ->modalHeading(fn (ServiceOrder $record) => __('admin_service_order.actions.view_agent_details').' - '.$record->order_number)
                        ->modalContent(fn (ServiceOrder $record) => view('filament.service-orders.agent-details', [
                            'order' => $record,
                            'device' => $record->getDeviceInfo(),
                        ]))
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel(__('admin_service_order.actions.close'))
                        ->modalWidth('lg')
                        ->slideOver(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceOrders::route('/'),
            'view' => ViewServiceOrder::route('/{record}'),
        ];
    }
}

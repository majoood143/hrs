<?php

namespace App\Filament\Resources\AuctionLotResource\RelationManagers;

use App\Models\AuctionBid;
use App\Models\SiteSetting;
use App\Services\Auctions\AuctionBidException;
use App\Services\Auctions\Bidding;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Every row of the lot's bidding, newest first, with each bidder's maximum (admins only). Bids are
 * never edited; a placed bid can be voided while the lot is open, and the lot is worked out again.
 */
class BidsRelationManager extends RelationManager
{
    protected static string $relationship = 'bids';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('auctions.admin.bids');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $money = fn ($state) => $state === null ? null : SiteSetting::formatCurrency($state);

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['bidder.customer', 'placedBy']))
            ->defaultSort('id', 'desc')
            ->poll('10s')
            ->columns([
                TextColumn::make('created_at')->label(__('auctions.admin.fields.time'))->dateTime('j M, H:i:s'),
                TextColumn::make('bidder.paddle_number')->label(__('auctions.admin.bidder'))->prefix('#')
                    ->description(fn (AuctionBid $record) => trim(($record->bidder?->customer?->name ?? '').' '.($record->bidder?->customer?->phone ?? '')) ?: null),
                TextColumn::make('kind')->label(__('auctions.admin.fields.kind'))->badge()
                    ->formatStateUsing(fn (string $state) => __('auctions.admin.kinds.'.$state))
                    ->color(fn (string $state) => ['bid' => 'primary', 'auto' => 'gray', 'max' => 'info'][$state] ?? 'gray'),
                TextColumn::make('amount')->label(__('auctions.admin.fields.amount'))->formatStateUsing($money)
                    ->extraAttributes(fn (AuctionBid $record) => $record->voided_at ? ['class' => 'line-through opacity-60'] : []),
                TextColumn::make('max_amount')->label(__('auctions.admin.fields.max_amount'))->formatStateUsing($money)->placeholder('—'),
                TextColumn::make('placedBy.name')->label(__('auctions.admin.fields.placed_by'))->icon('heroicon-o-phone')->placeholder('—')->toggleable(),
                TextColumn::make('void_reason')->label(__('auctions.admin.fields.voided'))->placeholder('—')->color('danger')->wrap()
                    ->description(fn (AuctionBid $record) => $record->voided_at?->format('j M, H:i')),
            ])
            ->filters([
                TernaryFilter::make('voided_at')->label(__('auctions.admin.fields.voided'))->nullable(),
            ])
            ->recordActions([
                Action::make('void')
                    ->label(__('auctions.admin.actions.void'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalDescription(__('auctions.admin.void_help'))
                    ->visible(fn (AuctionBid $record) => $record->voided_at === null && $record->kind !== 'auto'
                        && $this->getOwnerRecord()->status === 'ready'
                        && auth()->user()?->can('update', $this->getOwnerRecord()))
                    ->schema([
                        Textarea::make('reason')->label(__('auctions.admin.fields.void_reason'))->placeholder(__('auctions.admin.void_reason_placeholder'))->required()->maxLength(255),
                    ])
                    ->action(function (AuctionBid $record, array $data) {
                        try {
                            app(Bidding::class)->void($record, auth()->user(), $data['reason']);
                            Notification::make()->success()->title(__('auctions.admin.bid_voided'))->send();
                        } catch (AuctionBidException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ]);
    }
}

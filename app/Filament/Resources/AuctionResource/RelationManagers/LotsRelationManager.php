<?php

namespace App\Filament\Resources\AuctionResource\RelationManagers;

use App\Filament\Resources\AuctionLotResource;
use App\Models\AuctionLot;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The auction's lots, in catalogue order; each opens on the lot's own page (Admin → Auctions → Lots). */
class LotsRelationManager extends RelationManager
{
    protected static string $relationship = 'lots';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('auctions.admin.lots');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('invoice'))
            ->defaultSort('lot_number')
            ->columns(AuctionLotResource::lotColumns(withAuction: false))
            ->headerActions([
                Action::make('addLot')->label(__('auctions.admin.add_lot'))->icon('heroicon-o-plus')
                    ->url(fn () => AuctionLotResource::getUrl('create', ['auction' => $this->getOwnerRecord()->getKey()]))
                    ->visible(fn () => auth()->user()?->can('create', AuctionLot::class)),
            ])
            ->recordUrl(fn (AuctionLot $record) => AuctionLotResource::getUrl('edit', ['record' => $record]));
    }
}

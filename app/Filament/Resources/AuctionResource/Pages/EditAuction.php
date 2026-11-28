<?php

namespace App\Filament\Resources\AuctionResource\Pages;

use App\Filament\Resources\AuctionResource;
use App\Models\Auction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAuction extends EditRecord
{
    protected static string $resource = AuctionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // an auction with bids is cancelled (its status), never deleted: the bids are its record
            DeleteAction::make()->hidden(fn (Auction $record) => $record->lots()->where('bids_count', '>', 0)->exists()),
        ];
    }
}

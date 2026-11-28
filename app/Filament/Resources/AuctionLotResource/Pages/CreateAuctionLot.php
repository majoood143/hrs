<?php

namespace App\Filament\Resources\AuctionLotResource\Pages;

use App\Filament\Resources\AuctionLotResource;
use App\Filament\Resources\AuctionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAuctionLot extends CreateRecord
{
    protected static string $resource = AuctionLotResource::class;

    /** Back to the auction's lots. */
    protected function getRedirectUrl(): string
    {
        return AuctionResource::getUrl('edit', ['record' => $this->getRecord()->auction_id]);
    }
}

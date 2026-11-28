<?php

namespace App\Filament\Resources\AuctionResource\Pages;

use App\Filament\Resources\AuctionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAuction extends CreateRecord
{
    protected static string $resource = AuctionResource::class;

    /** On to the edit page, where the lots and bidders are added. */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}

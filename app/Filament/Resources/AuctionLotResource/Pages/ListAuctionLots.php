<?php

namespace App\Filament\Resources\AuctionLotResource\Pages;

use App\Filament\Resources\AuctionLotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAuctionLots extends ListRecords
{
    protected static string $resource = AuctionLotResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->icon('heroicon-o-plus')];
    }
}

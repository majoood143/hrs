<?php

namespace App\Filament\Resources\AuctionResource\Pages;

use App\Filament\Resources\AuctionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAuctions extends ListRecords
{
    protected static string $resource = AuctionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->icon('heroicon-o-plus')];
    }
}

<?php

namespace App\Filament\Resources\AuctionOrganizerResource\Pages;

use App\Filament\Resources\AuctionOrganizerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAuctionOrganizers extends ManageRecords
{
    protected static string $resource = AuctionOrganizerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->icon('heroicon-o-plus')];
    }
}

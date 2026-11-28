<?php

namespace App\Filament\Resources\GameCampaignResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\GameCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGameCampaigns extends ListRecords
{
    use HasExportActions;

    protected static string $resource = GameCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->icon('heroicon-o-plus')];
    }
}

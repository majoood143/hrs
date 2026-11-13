<?php

namespace App\Filament\Resources\PushCampaignResource\Pages;

use App\Filament\Resources\PushCampaignResource;
use App\Filament\Resources\PushCampaignResource\Widgets\CampaignFunnel;
use App\Filament\Resources\PushCampaignResource\Widgets\CampaignOpensChart;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** A campaign's report: what it said, who it went to, the funnel and when it was opened. */
class ViewPushCampaign extends ViewRecord
{
    protected static string $resource = PushCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn () => $this->record->editable()),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [CampaignFunnel::class, CampaignOpensChart::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}

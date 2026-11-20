<?php

namespace App\Filament\Resources\MobileAnnouncementResource\Pages;

use App\Filament\Resources\MobileAnnouncementResource;
use App\Filament\Resources\MobileAnnouncementResource\Widgets\AnnouncementActivityChart;
use App\Filament\Resources\MobileAnnouncementResource\Widgets\AnnouncementOutcomes;
use App\Filament\Resources\MobileAnnouncementResource\Widgets\AnnouncementStatsOverview;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMobileAnnouncement extends EditRecord
{
    protected static string $resource = MobileAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFooterWidgets(): array
    {
        return [AnnouncementStatsOverview::class, AnnouncementActivityChart::class, AnnouncementOutcomes::class];
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 3;
    }
}

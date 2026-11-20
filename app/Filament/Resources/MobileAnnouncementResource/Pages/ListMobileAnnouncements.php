<?php

namespace App\Filament\Resources\MobileAnnouncementResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\MobileAnnouncementResource;
use App\Filament\Resources\MobileAnnouncementResource\Widgets\AnnouncementActivityChart;
use App\Filament\Resources\MobileAnnouncementResource\Widgets\AnnouncementOutcomes;
use App\Filament\Resources\MobileAnnouncementResource\Widgets\AnnouncementStatsOverview;
use App\Filament\Resources\MobileAnnouncementResource\Widgets\TopAnnouncements;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMobileAnnouncements extends ListRecords
{
    use HasExportActions;

    protected static string $resource = MobileAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [AnnouncementStatsOverview::class, AnnouncementActivityChart::class, AnnouncementOutcomes::class, TopAnnouncements::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 3;
    }
}

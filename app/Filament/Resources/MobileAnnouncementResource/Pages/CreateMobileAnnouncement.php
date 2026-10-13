<?php

namespace App\Filament\Resources\MobileAnnouncementResource\Pages;

use App\Filament\Resources\MobileAnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMobileAnnouncement extends CreateRecord
{
    protected static string $resource = MobileAnnouncementResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

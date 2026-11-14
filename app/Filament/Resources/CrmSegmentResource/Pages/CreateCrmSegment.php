<?php

namespace App\Filament\Resources\CrmSegmentResource\Pages;

use App\Filament\Resources\CrmSegmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCrmSegment extends CreateRecord
{
    protected static string $resource = CrmSegmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

<?php

namespace App\Filament\Resources\CrmSegmentResource\Pages;

use App\Filament\Resources\CrmSegmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCrmSegments extends ListRecords
{
    protected static string $resource = CrmSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

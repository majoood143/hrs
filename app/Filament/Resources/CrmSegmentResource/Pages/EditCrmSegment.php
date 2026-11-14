<?php

namespace App\Filament\Resources\CrmSegmentResource\Pages;

use App\Filament\Resources\CrmSegmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCrmSegment extends EditRecord
{
    protected static string $resource = CrmSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

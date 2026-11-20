<?php

namespace App\Filament\Resources\StallionResource\Pages;

use App\Filament\Resources\StallionResource;
use App\Filament\Support\ListingViews\ListingViewWidgets;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStallion extends EditRecord
{
    protected static string $resource = StallionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getFooterWidgets(): array
    {
        return ListingViewWidgets::forRecord(static::$resource);
    }
}

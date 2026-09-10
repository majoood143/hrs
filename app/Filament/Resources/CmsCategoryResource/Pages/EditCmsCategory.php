<?php

namespace App\Filament\Resources\CmsCategoryResource\Pages;

use App\Filament\Resources\CmsCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCmsCategory extends EditRecord
{
    protected static string $resource = CmsCategoryResource::class;

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
}

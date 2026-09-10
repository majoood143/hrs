<?php

namespace App\Filament\Resources\CmsPostResource\Pages;

use App\Filament\Resources\CmsPostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCmsPost extends EditRecord
{
    protected static string $resource = CmsPostResource::class;

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

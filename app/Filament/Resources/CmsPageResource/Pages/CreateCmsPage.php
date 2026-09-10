<?php

namespace App\Filament\Resources\CmsPageResource\Pages;

use App\Filament\Resources\CmsPageResource;
use App\Models\CmsPage;
use Filament\Resources\Pages\CreateRecord;

class CreateCmsPage extends CreateRecord
{
    protected static string $resource = CmsPageResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        if ($this->record->is_homepage) {
            CmsPage::query()->where('id', '!=', $this->record->id)->update(['is_homepage' => false]);
        }
    }
}

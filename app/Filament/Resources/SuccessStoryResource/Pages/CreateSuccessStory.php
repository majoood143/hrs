<?php

namespace App\Filament\Resources\SuccessStoryResource\Pages;

use App\Filament\Resources\SuccessStoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSuccessStory extends CreateRecord
{
    protected static string $resource = SuccessStoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

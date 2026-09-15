<?php

namespace App\Filament\Resources\VideoFolderResource\Pages;

use App\Filament\Resources\VideoFolderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVideoFolder extends CreateRecord
{
    protected static string $resource = VideoFolderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

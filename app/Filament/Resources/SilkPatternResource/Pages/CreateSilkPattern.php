<?php

namespace App\Filament\Resources\SilkPatternResource\Pages;

use App\Filament\Resources\SilkPatternResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSilkPattern extends CreateRecord
{
    protected static string $resource = SilkPatternResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

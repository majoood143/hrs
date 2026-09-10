<?php

namespace App\Filament\Resources\HorseSalePostResource\Pages;

use App\Filament\Resources\HorseSalePostResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateHorseSalePost extends CreateRecord
{
    protected static string $resource = HorseSalePostResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

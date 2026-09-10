<?php

namespace App\Filament\Resources\ToolSalePostResource\Pages;

use App\Filament\Resources\ToolSalePostResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateToolSalePost extends CreateRecord
{
    protected static string $resource = ToolSalePostResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

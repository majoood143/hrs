<?php

namespace App\Filament\Resources\HorseSalePostResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\HorseSalePostResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHorseSalePosts extends ListRecords
{
    protected static string $resource = HorseSalePostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

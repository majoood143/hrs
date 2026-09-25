<?php

namespace App\Filament\Resources\ServiceOrderResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\ServiceOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListServiceOrders extends ListRecords
{
    use HasExportActions;

    protected static string $resource = ServiceOrderResource::class;
}

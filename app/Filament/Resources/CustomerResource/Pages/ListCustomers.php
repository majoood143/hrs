<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    use HasExportActions;

    protected static string $resource = CustomerResource::class;
}

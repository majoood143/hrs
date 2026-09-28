<?php

namespace App\Filament\Stable\Resources\StableBookings\Pages;

use App\Filament\Stable\Resources\StableBookings\StableBookingResource;
use Filament\Resources\Pages\ListRecords;

class ListStableBookings extends ListRecords
{
    protected static string $resource = StableBookingResource::class;
}

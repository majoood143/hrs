<?php

namespace App\Filament\Resources\NotificationLogResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\NotificationLogResource;
use Filament\Resources\Pages\ListRecords;

class ListNotificationLogs extends ListRecords
{
    use HasExportActions;

    protected static string $resource = NotificationLogResource::class;
}

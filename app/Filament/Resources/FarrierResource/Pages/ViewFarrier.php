<?php

namespace App\Filament\Resources\FarrierResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\FarrierResource;
use App\Filament\Support\PendingPosts;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFarrier extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = FarrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PendingPosts::approveAction(),
            EditAction::make(),
        ];
    }
}

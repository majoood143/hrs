<?php

namespace App\Filament\Resources\HorseSalePostResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\HorseSalePostResource;
use App\Filament\Support\PendingPosts;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHorseSalePost extends ViewRecord
{
    use HasExportActions;

    protected static string $resource = HorseSalePostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PendingPosts::approveAction(),
            EditAction::make(),
        ];
    }
}

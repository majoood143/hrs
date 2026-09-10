<?php

namespace App\Filament\Resources\HorseSalePostResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\HorseSalePostResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewHorseSalePost extends ViewRecord
{
    protected static string $resource = HorseSalePostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}

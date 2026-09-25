<?php

namespace App\Filament\Resources\AuthorityResource\Pages;

use App\Filament\Concerns\HasExportActions;
use Filament\Actions\CreateAction;
use App\Filament\Resources\AuthorityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAuthorities extends ListRecords
{
    use HasExportActions;

    protected static string $resource = AuthorityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

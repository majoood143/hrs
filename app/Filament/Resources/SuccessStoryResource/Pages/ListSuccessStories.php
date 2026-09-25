<?php

namespace App\Filament\Resources\SuccessStoryResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\SuccessStoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuccessStories extends ListRecords
{
    use HasExportActions;

    protected static string $resource = SuccessStoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

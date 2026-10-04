<?php

namespace App\Filament\Resources\SilkPatternResource\Pages;

use App\Filament\Resources\SilkPatternResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSilkPattern extends EditRecord
{
    protected static string $resource = SilkPatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

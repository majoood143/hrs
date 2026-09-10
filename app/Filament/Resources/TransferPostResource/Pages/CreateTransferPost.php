<?php

namespace App\Filament\Resources\TransferPostResource\Pages;

use App\Filament\Resources\TransferPostResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTransferPost extends CreateRecord
{
    protected static string $resource = TransferPostResource::class;

    protected function getRedirectUrl(): string
    { // Redirect to the list page after creation
       return $this->getResource()::getUrl('index');
    }
}

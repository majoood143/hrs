<?php

namespace App\Filament\Public\Resources\TransferPostResource\Pages;

use App\Filament\Public\Resources\TransferPostResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransferPost extends CreateRecord
{
    protected static string $resource = TransferPostResource::class;

    public function getTitle(): string
    {
        return __('transportation.post_a_transfer');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

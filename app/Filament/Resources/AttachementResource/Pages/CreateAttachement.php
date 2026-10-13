<?php

namespace App\Filament\Resources\AttachementResource\Pages;

use App\Filament\Resources\AttachementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttachement extends CreateRecord
{
    protected static string $resource = AttachementResource::class;

    /** The uploader is whoever is signed in (`user_id` is required). */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'user_id' => auth()->id()];
    }
}

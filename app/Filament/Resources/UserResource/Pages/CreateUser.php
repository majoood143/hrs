<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    { // Redirect to the list page after creation
       return $this->getResource()::getUrl('index');   
    }

    // public function mutateFormDataBeforeCreate(array $data): array
    // {
    //     // Example: Check for duplicate based on 'name' and 'category_id'
    //     if (User::where('name', $data['name'])
    //         ->where('email', $data['email'])
    //         ->exists()
    //     ) {
    //         throw new \Exception('A record with this name and category already exists.');
    //     }
    //     return $data;
    // }
}

<?php

namespace App\Filament\Public\Resources\TransferPostResource\Pages;

use App\Filament\Public\Resources\TransferPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTransferPosts extends ListRecords
{
    protected static string $resource = TransferPostResource::class;

    public function getTitle(): string
    {
        return __('transportation.board_title');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('transportation.post_a_transfer')),
        ];
    }
}

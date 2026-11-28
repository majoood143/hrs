<?php

namespace App\Filament\Stable\Resources\StableExpenses\Pages;

use App\Filament\Stable\Resources\StableExpenses\StableExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStableExpenses extends ManageRecords
{
    protected static string $resource = StableExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->mutateDataUsing(fn (array $data) => [...$data, 'user_id' => auth()->id()]),
        ];
    }
}

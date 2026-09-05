<?php

namespace App\Filament\Resources\HorseResource\Pages;

use App\Filament\Resources\HorseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\filament\Widgets\StatsOverview;
use Filament\Resources\Components\Tab;



class ListHorses extends ListRecords
{
    protected static string $resource = HorseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class
        ];
    }

    // public function getTabs(): array
    // {
    //     return [
    //         null => Tab::make('All'),
    //         'Pure Arabian' => Tab::make()->query(fn($query) => $query->where('type_id', 1)),
    //         'Arabian' => Tab::make()->query(fn($query) => $query->where('type_id', 2)),
    //         'Thoroughbred' => Tab::make()->query(fn($query) => $query->where('type_id', 3)),
            
    //     ];
    // }

   
}

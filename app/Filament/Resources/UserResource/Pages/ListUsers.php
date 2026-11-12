<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\HasExportActions;
use App\Filament\Resources\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    use HasExportActions;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make(__('admin_user.tabs.all')),
            // "staff" = anyone with a Shield role, i.e. who can actually do something in /admin
            'staff' => Tab::make(__('admin_user.tabs.staff'))
                ->icon('heroicon-o-shield-check')
                ->query(fn (Builder $query) => $query->whereHas('roles')),
        ];

        foreach (UserResource::typeOptions() as $type => $label) {
            $tabs[$type] = Tab::make($label)->query(fn (Builder $query) => $query->where('type', $type));
        }

        return $tabs;
    }
}

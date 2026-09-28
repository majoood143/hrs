<?php

namespace App\Filament\Stable\Pages;

use App\Filament\Support\StableChangeLogTable;
use App\Models\Stable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/** The owner's view of their stable's change log, including what admins changed on their behalf. */
class ChangeLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.stable.pages.change-log';

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.setup');
    }

    public static function getNavigationLabel(): string
    {
        return __('stable_panel.log.title');
    }

    public function getTitle(): string
    {
        return __('stable_panel.log.title');
    }

    public static function canAccess(): bool
    {
        $stable = Filament::getTenant();

        return $stable instanceof Stable && Gate::allows('update', $stable);
    }

    public function table(Table $table): Table
    {
        return StableChangeLogTable::configure($table, (int) Filament::getTenant()->getKey());
    }
}

<?php

namespace App\Filament\Widgets\Stables;

use App\Filament\Support\StableChangeLogTable;
use App\Models\Stable;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

/** The stable's change log on its admin page (who changed what, admins on its behalf marked). */
class StableChangeLogWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    public ?Model $record = null;

    public function table(Table $table): Table
    {
        /** @var Stable $stable */
        $stable = $this->record;

        return StableChangeLogTable::configure($table, (int) $stable->getKey())
            ->heading(__('stable_panel.log.title'));
    }
}

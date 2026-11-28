<?php

namespace App\Filament\Resources\StableResource\RelationManagers;

use App\Filament\Stable\Resources\StableExtras\StableExtraResource;
use App\Models\StableExtra;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** The stable's extras, with the owner's own form: admins can add, change, switch off or delete them. */
class ExtrasRelationManager extends RelationManager
{
    protected static string $relationship = 'extras';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('stable_extras.admin.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return StableExtra::enabled() && Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return ! Gate::allows('update', $this->getOwnerRecord());
    }

    public function form(Schema $schema): Schema
    {
        return StableExtraResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return StableExtraResource::table($table)
            ->recordTitleAttribute('en_name')
            ->headerActions([CreateAction::make()->icon('heroicon-o-plus')]);
    }
}

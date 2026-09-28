<?php

namespace App\Filament\Resources\StableResource\RelationManagers;

use App\Models\SiteSetting;
use App\Models\StableOffering;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** What the stable's owner has put up for booking. Read-only here: owners manage it from /stable. */
class OfferingsRelationManager extends RelationManager
{
    protected static string $relationship = 'offerings';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin_stable.offerings.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('en_name')
            ->columns([
                TextColumn::make('en_name')
                    ->label(__('admin_stable.offerings.name'))
                    ->description(fn (StableOffering $record) => $record->type?->label()),
                TextColumn::make('price')
                    ->label(__('admin_stable.offerings.price'))
                    ->formatStateUsing(fn ($state) => SiteSetting::formatCurrencyHtml($state, 3)),
                TextColumn::make('duration_minutes')
                    ->label(__('admin_stable.offerings.duration'))
                    ->suffix(' '.__('stable_panel.units.min')),
                TextColumn::make('capacity')
                    ->label(__('admin_stable.offerings.capacity')),
                IconColumn::make('is_active')
                    ->label(__('admin_stable.offerings.active'))
                    ->boolean(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\HorseResource\RelationManagers;

use App\Filament\Resources\VaccinationResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** A horse's vaccinations on its page. */
class VaccinationRelationManager extends RelationManager
{
    protected static string $relationship = 'vaccination';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin_vaccination.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_vaccination.navigation.label');
    }

    private static function label(string $key): string
    {
        return __("admin_vaccination.fields.{$key}");
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(VaccinationResource::fields())->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('vaccine_type')
            ->defaultSort('date_administered', 'desc')
            ->columns([
                TextColumn::make('vaccine_type')->label(static::label('vaccine_type'))->searchable(),
                TextColumn::make('date_administered')->label(static::label('date_administered'))->date()->sortable(),
                TextColumn::make('manufacturer')->label(static::label('manufacturer'))->placeholder('—'),
                TextColumn::make('batch_number')->label(static::label('batch_number'))->placeholder('—'),
                TextColumn::make('veterinarian_name')->label(static::label('veterinarian_name'))->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Resources\RegionResource\RelationManagers;

use App\Filament\Support\InUseDeleteActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CityRelationManager extends RelationManager
{
    protected static string $relationship = 'city';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin_city.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_city.navigation.label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('en_name')
                    ->label(__('admin_locations.fields.en_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('ar_name')
                    ->label(__('admin_locations.fields.ar_name'))
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['dir' => 'rtl']),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute(app()->getLocale() === 'ar' ? 'ar_name' : 'en_name')
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('en_name')
                    ->label(__('admin_locations.fields.en_name'))
                    ->searchable(),
                TextColumn::make('ar_name')
                    ->label(__('admin_locations.fields.ar_name'))
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                InUseDeleteActions::delete('admin_locations'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    InUseDeleteActions::bulk('admin_locations'),
                ]),
            ]);
    }
}

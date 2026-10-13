<?php

namespace App\Filament\Resources\CityResource\RelationManagers;

use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The horses in a city. Read and rename only: a horse needs a type, gender, owner and location,
 * so it is added on the Horses screen (a create button here could never succeed).
 */
class HorseRelationManager extends RelationManager
{
    protected static string $relationship = 'horse';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin_horse.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_horse.navigation.label');
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
                TextColumn::make('age')
                    ->label(__('admin_locations.horses.age'))
                    ->suffix(' '.__('admin_locations.horses.years')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

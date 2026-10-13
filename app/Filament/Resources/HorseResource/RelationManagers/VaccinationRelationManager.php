<?php

namespace App\Filament\Resources\HorseResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
        return $schema
            ->components([
                TextInput::make('vaccine_type')
                    ->label(static::label('vaccine_type'))
                    ->required()
                    ->maxLength(255),
                DatePicker::make('date_administered')
                    ->label(static::label('date_administered'))
                    ->maxDate(now())
                    ->required(),
                TextInput::make('manufacturer')
                    ->label(static::label('manufacturer'))
                    ->maxLength(255),
                TextInput::make('batch_number')
                    ->label(static::label('batch_number'))
                    ->maxLength(255),
                TextInput::make('veterinarian_name')
                    ->label(static::label('veterinarian_name'))
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label(static::label('notes'))
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
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

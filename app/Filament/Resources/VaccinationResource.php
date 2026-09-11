<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\VaccinationResource\Pages\ListVaccinations;
use App\Filament\Resources\VaccinationResource\Pages\CreateVaccination;
use App\Filament\Resources\VaccinationResource\Pages\ViewVaccination;
use App\Filament\Resources\VaccinationResource\Pages\EditVaccination;
use App\Filament\Resources\VaccinationResource\Pages;
use App\Filament\Resources\VaccinationResource\RelationManagers;
use App\Models\Vaccination;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VaccinationResource extends Resource
{
    protected static ?string $model = Vaccination::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-puzzle-piece';

    public static function getModelLabel(): string
    {
        return __('admin_vaccination.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_vaccination.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_vaccination.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('vaccine_type')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('date_administered')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vaccine_type')
                    ->searchable(),
                TextColumn::make('date_administered')
                    ->date()
                    ->sortable(),
            TextColumn::make('next_due_date')
                ->date()
                ->sortable(),
                    
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVaccinations::route('/'),
            'create' => CreateVaccination::route('/create'),
            'view' => ViewVaccination::route('/{record}'),
            'edit' => EditVaccination::route('/{record}/edit'),
        ];
    }
}

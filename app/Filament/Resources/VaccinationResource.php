<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VaccinationResource\Pages\CreateVaccination;
use App\Filament\Resources\VaccinationResource\Pages\EditVaccination;
use App\Filament\Resources\VaccinationResource\Pages\ListVaccinations;
use App\Filament\Resources\VaccinationResource\Pages\ViewVaccination;
use App\Filament\Support\LocationFields;
use App\Models\Vaccination;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class VaccinationResource extends Resource
{
    protected static ?string $model = Vaccination::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

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

    private static function label(string $key): string
    {
        return __("admin_vaccination.fields.{$key}");
    }

    /** The vaccination itself: here and on the horse's page (VaccinationRelationManager). */
    public static function fields(): array
    {
        return [
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
        ];
    }

    public static function form(Schema $schema): Schema
    {
        $name = LocationFields::nameColumn();

        return $schema
            ->components([
                // every vaccination belongs to a horse (the horse page lists them)
                Select::make('horse_id')
                    ->label(static::label('horse'))
                    ->relationship('horse', $name)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull(),
                ...static::fields(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date_administered', 'desc')
            ->columns([
                TextColumn::make('horse.'.LocationFields::nameColumn())
                    ->label(static::label('horse'))
                    ->searchable(['en_name', 'ar_name']),
                TextColumn::make('vaccine_type')
                    ->label(static::label('vaccine_type'))
                    ->searchable(),
                TextColumn::make('date_administered')
                    ->label(static::label('date_administered'))
                    ->date()
                    ->sortable(),
                TextColumn::make('veterinarian_name')
                    ->label(static::label('veterinarian_name'))
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(static::label('created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

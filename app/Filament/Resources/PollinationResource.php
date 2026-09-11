<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\PollinationResource\Pages\ListPollinations;
use App\Filament\Resources\PollinationResource\Pages\CreatePollination;
use App\Filament\Resources\PollinationResource\Pages\ViewPollination;
use App\Filament\Resources\PollinationResource\Pages\EditPollination;
use App\Filament\Resources\PollinationResource\Pages;
use App\Filament\Resources\PollinationResource\RelationManagers;
use App\Models\Pollination;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class PollinationResource extends Resource
{
    protected static ?string $model = Pollination::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-newspaper';

    public static function getModelLabel(): string
    {
        return __('admin_pollination.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_pollination.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_pollination.navigation.plural');
    }

     public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('en_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('ar_name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('en_name')
                    ->searchable(),
                TextColumn::make('ar_name')
                    ->searchable(),
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
                ActionGroup::make([
                ViewAction::make(),
                EditAction::make(),
                ])
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
            'index' => ListPollinations::route('/'),
            'create' => CreatePollination::route('/create'),
            'view' => ViewPollination::route('/{record}'),
            'edit' => EditPollination::route('/{record}/edit'),
        ];
    }
}

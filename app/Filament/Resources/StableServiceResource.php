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
use App\Filament\Resources\StableServiceResource\Pages\ListStableServices;
use App\Filament\Resources\StableServiceResource\Pages\CreateStableService;
use App\Filament\Resources\StableServiceResource\Pages\ViewStableService;
use App\Filament\Resources\StableServiceResource\Pages\EditStableService;
use App\Filament\Resources\StableServiceResource\Pages;
use App\Models\StableService;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class StableServiceResource extends Resource
{
    protected static ?string $model = StableService::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-wrench-screwdriver';

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
            'index' => ListStableServices::route('/'),
            'create' => CreateStableService::route('/create'),
            'view' => ViewStableService::route('/{record}'),
            'edit' => EditStableService::route('/{record}/edit'),
        ];
    }
}

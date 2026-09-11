<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ColorColumn;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ColorResource\Pages\ListColors;
use App\Filament\Resources\ColorResource\Pages\CreateColor;
use App\Filament\Resources\ColorResource\Pages\ViewColor;
use App\Filament\Resources\ColorResource\Pages\EditColor;
use App\Filament\Resources\ColorResource\Pages;
use App\Filament\Resources\ColorResource\RelationManagers;
use App\Models\Color;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\ColorPicker;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ColorEntry;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;

class ColorResource extends Resource
{
    protected static ?string $model = Color::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-swatch';

    public static function getModelLabel(): string
    {
        return __('admin_color.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_color.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_color.navigation.plural');
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
                ColorPicker::make('hex_code')
                    ->required()
                    ->regex('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})\b$/'),
                    
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('en_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ar_name')
                    ->searchable()
                    ->sortable(),
                    ColorColumn::make('hex_code')
                    ->searchable()
                ->copyable(),
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
                ActivityLogTimelineTableAction::make('Activities'),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
            // ...
            TextEntry::make('en_name'),
            TextEntry::make('ar_name'),
            //     ->label('Name')
            //     ->content(fn (Color $record): string => $record->name),
             ColorEntry::make('hex_code')
            //     ->label('Color')
            //     ->content(fn (Color $record): string => $record->hex_code)
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
            'index' => ListColors::route('/'),
            'create' => CreateColor::route('/create'),
            'view' => ViewColor::route('/{record}'),
            'edit' => EditColor::route('/{record}/edit'),
        ];
    }
}

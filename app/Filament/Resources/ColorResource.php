<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ColorResource\Pages\CreateColor;
use App\Filament\Resources\ColorResource\Pages\EditColor;
use App\Filament\Resources\ColorResource\Pages\ListColors;
use App\Filament\Resources\ColorResource\Pages\ViewColor;
use App\Filament\Support\LookupSchema;
use App\Models\Color;
use Filament\Forms\Components\ColorPicker;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Table;
use Rmsramos\Activitylog\Actions\ActivityLogTimelineTableAction;

class ColorResource extends Resource
{
    protected static ?string $model = Color::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

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
        return (string) static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return LookupSchema::form($schema, [
            ColorPicker::make('hex_code')
                ->label(__('admin_lookups.fields.hex_code'))
                ->required()
                ->regex('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return LookupSchema::table($table, [
            ColorColumn::make('hex_code')
                ->label(__('admin_lookups.fields.hex_code'))
                ->searchable()
                ->copyable(),
        ], [
            ActivityLogTimelineTableAction::make('Activities')
                ->label(__('admin_lookups.fields.activities')),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('en_name')->label(__('admin_lookups.fields.en_name')),
                TextEntry::make('ar_name')->label(__('admin_lookups.fields.ar_name')),
                ColorEntry::make('hex_code')->label(__('admin_lookups.fields.hex_code'))->copyable(),
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

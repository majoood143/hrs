<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CenterResource\Pages\CreateCenter;
use App\Filament\Resources\CenterResource\Pages\EditCenter;
use App\Filament\Resources\CenterResource\Pages\ListCenters;
use App\Filament\Resources\CenterResource\Pages\ViewCenter;
use App\Filament\Support\DirectoryPlaceSchema;
use App\Models\Center;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CenterResource extends Resource
{
    protected static ?string $model = Center::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.directory');
    }

    public static function getModelLabel(): string
    {
        return __('admin_center.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_center.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_center.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return DirectoryPlaceSchema::form($schema, Center::class, 'centers', 'training');
    }

    public static function table(Table $table): Table
    {
        return DirectoryPlaceSchema::table($table, Center::class, 'centers', 'centers.show');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCenters::route('/'),
            'create' => CreateCenter::route('/create'),
            'view' => ViewCenter::route('/{record}'),
            'edit' => EditCenter::route('/{record}/edit'),
        ];
    }
}

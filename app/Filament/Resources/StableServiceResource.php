<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StableServiceResource\Pages\CreateStableService;
use App\Filament\Resources\StableServiceResource\Pages\EditStableService;
use App\Filament\Resources\StableServiceResource\Pages\ListStableServices;
use App\Filament\Resources\StableServiceResource\Pages\ViewStableService;
use App\Filament\Support\LookupSchema;
use App\Models\StableService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class StableServiceResource extends Resource
{
    protected static ?string $model = StableService::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    public static function getModelLabel(): string
    {
        return __('admin_stable_service.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_stable_service.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_stable_service.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return LookupSchema::form($schema);
    }

    public static function table(Table $table): Table
    {
        return LookupSchema::table($table);
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

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CenterServiceResource\Pages\CreateCenterService;
use App\Filament\Resources\CenterServiceResource\Pages\EditCenterService;
use App\Filament\Resources\CenterServiceResource\Pages\ListCenterServices;
use App\Filament\Resources\CenterServiceResource\Pages\ViewCenterService;
use App\Filament\Support\DirectoryPlaceSchema;
use App\Models\CenterService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CenterServiceResource extends Resource
{
    protected static ?string $model = CenterService::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function getModelLabel(): string
    {
        return __('admin_center_service.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_center_service.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_center_service.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return DirectoryPlaceSchema::serviceForm($schema, CenterService::class, 'centers', 'all');
    }

    public static function table(Table $table): Table
    {
        return DirectoryPlaceSchema::serviceTable($table, CenterService::class, 'centers');
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
            'index' => ListCenterServices::route('/'),
            'create' => CreateCenterService::route('/create'),
            'view' => ViewCenterService::route('/{record}'),
            'edit' => EditCenterService::route('/{record}/edit'),
        ];
    }
}

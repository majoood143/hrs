<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClinicServiceResource\Pages\CreateClinicService;
use App\Filament\Resources\ClinicServiceResource\Pages\EditClinicService;
use App\Filament\Resources\ClinicServiceResource\Pages\ListClinicServices;
use App\Filament\Resources\ClinicServiceResource\Pages\ViewClinicService;
use App\Filament\Support\DirectoryPlaceSchema;
use App\Models\ClinicService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ClinicServiceResource extends Resource
{
    protected static ?string $model = ClinicService::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function getModelLabel(): string
    {
        return __('admin_clinic_service.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_clinic_service.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_clinic_service.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return DirectoryPlaceSchema::serviceForm($schema, ClinicService::class, 'clinics', 'both');
    }

    public static function table(Table $table): Table
    {
        return DirectoryPlaceSchema::serviceTable($table, ClinicService::class, 'clinics');
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
            'index' => ListClinicServices::route('/'),
            'create' => CreateClinicService::route('/create'),
            'view' => ViewClinicService::route('/{record}'),
            'edit' => EditClinicService::route('/{record}/edit'),
        ];
    }
}

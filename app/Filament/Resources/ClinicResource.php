<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClinicResource\Pages\CreateClinic;
use App\Filament\Resources\ClinicResource\Pages\EditClinic;
use App\Filament\Resources\ClinicResource\Pages\ListClinics;
use App\Filament\Resources\ClinicResource\Pages\ViewClinic;
use App\Filament\Support\DirectoryPlaceSchema;
use App\Models\Clinic;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ClinicResource extends Resource
{
    protected static ?string $model = Clinic::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-plus-circle';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.directory');
    }

    public static function getModelLabel(): string
    {
        return __('admin_clinic.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_clinic.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_clinic.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return DirectoryPlaceSchema::form($schema, Clinic::class, 'clinics', 'clinic');
    }

    public static function table(Table $table): Table
    {
        return DirectoryPlaceSchema::table($table, Clinic::class, 'clinics', 'clinics.show');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClinics::route('/'),
            'create' => CreateClinic::route('/create'),
            'view' => ViewClinic::route('/{record}'),
            'edit' => EditClinic::route('/{record}/edit'),
        ];
    }
}

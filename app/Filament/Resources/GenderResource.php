<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GenderResource\Pages\CreateGender;
use App\Filament\Resources\GenderResource\Pages\EditGender;
use App\Filament\Resources\GenderResource\Pages\ListGenders;
use App\Filament\Resources\GenderResource\Pages\ViewGender;
use App\Filament\Support\LookupSchema;
use App\Models\Gender;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class GenderResource extends Resource
{
    protected static ?string $model = Gender::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    public static function getModelLabel(): string
    {
        return __('admin_gender.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_gender.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_gender.navigation.plural');
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
            'index' => ListGenders::route('/'),
            'create' => CreateGender::route('/create'),
            'view' => ViewGender::route('/{record}'),
            'edit' => EditGender::route('/{record}/edit'),
        ];
    }
}

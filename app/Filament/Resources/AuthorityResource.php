<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuthorityResource\Pages\CreateAuthority;
use App\Filament\Resources\AuthorityResource\Pages\EditAuthority;
use App\Filament\Resources\AuthorityResource\Pages\ListAuthorities;
use App\Filament\Resources\AuthorityResource\Pages\ViewAuthority;
use App\Filament\Support\LookupSchema;
use App\Models\Authority;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AuthorityResource extends Resource
{
    protected static ?string $model = Authority::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    public static function getModelLabel(): string
    {
        return __('admin_authority.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_authority.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_authority.navigation.plural');
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
            'index' => ListAuthorities::route('/'),
            'create' => CreateAuthority::route('/create'),
            'view' => ViewAuthority::route('/{record}'),
            'edit' => EditAuthority::route('/{record}/edit'),
        ];
    }
}

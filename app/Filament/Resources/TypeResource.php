<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TypeResource\Pages\CreateType;
use App\Filament\Resources\TypeResource\Pages\EditType;
use App\Filament\Resources\TypeResource\Pages\ListTypes;
use App\Filament\Resources\TypeResource\Pages\ViewType;
use App\Filament\Support\LookupSchema;
use App\Models\Type;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class TypeResource extends Resource
{
    protected static ?string $model = Type::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bookmark';

    public static function getModelLabel(): string
    {
        return __('admin_type.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_type.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_type.navigation.plural');
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
            'index' => ListTypes::route('/'),
            'create' => CreateType::route('/create'),
            'view' => ViewType::route('/{record}'),
            'edit' => EditType::route('/{record}/edit'),
        ];
    }
}

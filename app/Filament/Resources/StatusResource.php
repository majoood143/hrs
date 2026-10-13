<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StatusResource\Pages\CreateStatus;
use App\Filament\Resources\StatusResource\Pages\EditStatus;
use App\Filament\Resources\StatusResource\Pages\ListStatuses;
use App\Filament\Resources\StatusResource\Pages\ViewStatus;
use App\Filament\Support\LookupSchema;
use App\Models\Status;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class StatusResource extends Resource
{
    protected static ?string $model = Status::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    public static function getModelLabel(): string
    {
        return __('admin_status.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_status.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_status.navigation.plural');
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
            'index' => ListStatuses::route('/'),
            'create' => CreateStatus::route('/create'),
            'view' => ViewStatus::route('/{record}'),
            'edit' => EditStatus::route('/{record}/edit'),
        ];
    }
}

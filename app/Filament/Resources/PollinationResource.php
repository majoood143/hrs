<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PollinationResource\Pages\CreatePollination;
use App\Filament\Resources\PollinationResource\Pages\EditPollination;
use App\Filament\Resources\PollinationResource\Pages\ListPollinations;
use App\Filament\Resources\PollinationResource\Pages\ViewPollination;
use App\Filament\Support\LookupSchema;
use App\Models\Pollination;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PollinationResource extends Resource
{
    protected static ?string $model = Pollination::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    public static function getModelLabel(): string
    {
        return __('admin_pollination.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_pollination.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_pollination.navigation.plural');
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
            'index' => ListPollinations::route('/'),
            'create' => CreatePollination::route('/create'),
            'view' => ViewPollination::route('/{record}'),
            'edit' => EditPollination::route('/{record}/edit'),
        ];
    }
}

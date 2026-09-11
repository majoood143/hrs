<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\SettingResource\Pages\ManageSettings;
use App\Filament\Resources\SettingResource\Pages;
use App\Filament\Resources\SettingResource\RelationManagers;
use App\Models\Setting;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return __('admin_navigation.settings');
    }

    public static function getModelLabel(): string
    {
        return __('admin_setting.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_setting.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_setting.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
            //
            TextInput::make('key')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Textarea::make('value')
                ->required(),
            TextInput::make('group')
                ->required()
                ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
            //
            TextColumn::make('key')
                ->searchable(),
            TextColumn::make('value')
                ->limit(50),
            TextColumn::make('group')
                ->searchable(),
            TextColumn::make('updated_at')
                ->dateTime()
                ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSettings::route('/'),
        ];
    }
}

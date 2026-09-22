<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ServiceResource\Pages\ListServices;
use App\Filament\Resources\ServiceResource\Pages\CreateService;
use App\Filament\Resources\ServiceResource\Pages\ViewService;
use App\Filament\Resources\ServiceResource\Pages\EditService;
use App\Filament\Resources\ServiceResource\Pages;
use App\Filament\Resources\ServiceResource\RelationManagers;
use App\Models\Service;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Summarizers\Average;
use Filament\Tables\Columns\Summarizers\Range;
use Filament\Forms\Components\RichEditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getModelLabel(): string
    {
        return __('admin_service.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_service.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_service.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin_service.fields.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('ar_name')
                    ->label(__('admin_service.fields.ar_name'))
                    ->extraInputAttributes(['dir' => 'rtl'])
                    ->maxLength(255),
                RichEditor::make('description')
                    ->label(__('admin_service.fields.description'))
                    ->columnSpanFull(),
                RichEditor::make('ar_description')
                    ->label(__('admin_service.fields.ar_description'))
                    ->extraInputAttributes(['dir' => 'rtl'])
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label(__('admin_service.fields.price'))
                    ->helperText(__('admin_service.fields.price_helper'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.001)
                    ->prefix(fn () => \App\Models\SiteSetting::currency()['symbol']),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin_service.fields.name'))
                    ->searchable(),
                TextColumn::make('ar_name')
                    ->label(__('admin_service.fields.ar_name'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('price')
                    ->label(__('admin_service.fields.price'))
                    ->formatStateUsing(fn ($state) => \App\Models\SiteSetting::formatCurrency($state, 3))
                    ->summarize([
                        Average::make(),
                        Range::make(),
                    ])
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->summarize([
                        Average::make(),
                        Range::make(),
                    ]),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
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
            'index' => ListServices::route('/'),
            'create' => CreateService::route('/create'),
            'view' => ViewService::route('/{record}'),
            'edit' => EditService::route('/{record}/edit'),
        ];
    }
}

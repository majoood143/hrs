<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventCategoryResource\Pages\CreateEventCategory;
use App\Filament\Resources\EventCategoryResource\Pages\EditEventCategory;
use App\Filament\Resources\EventCategoryResource\Pages\ListEventCategories;
use App\Models\EventCategory;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventCategoryResource extends Resource
{
    protected static ?string $model = EventCategory::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 81;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('event_categories.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('event_categories.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('event_categories.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('en_name')
                    ->label(__('event_categories.fields.en_name'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('ar_name')
                    ->label(__('event_categories.fields.ar_name'))
                    ->required()
                    ->maxLength(255)
                    ->extraInputAttributes(['dir' => 'rtl']),

                ColorPicker::make('color')
                    ->label(__('event_categories.fields.color'))
                    ->required(),

                TextInput::make('order')
                    ->label(__('event_categories.fields.order'))
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('en_name')
                    ->label(__('event_categories.fields.en_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ar_name')
                    ->label(__('event_categories.fields.ar_name'))
                    ->searchable()
                    ->sortable(),

                ColorColumn::make('color')
                    ->label(__('event_categories.fields.color')),

                TextColumn::make('order')
                    ->label(__('event_categories.fields.order'))
                    ->sortable(),

                TextColumn::make('events_count')
                    ->label(__('events.navigation.plural'))
                    ->counts('events'),
            ])
            ->defaultSort('order')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    \Filament\Actions\DeleteAction::make()
                        ->disabled(fn (EventCategory $record) => $record->events()->exists()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEventCategories::route('/'),
            'create' => CreateEventCategory::route('/create'),
            'edit' => EditEventCategory::route('/{record}/edit'),
        ];
    }
}

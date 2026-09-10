<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CmsMenuResource\Pages\CreateCmsMenu;
use App\Filament\Resources\CmsMenuResource\Pages\EditCmsMenu;
use App\Filament\Resources\CmsMenuResource\Pages\ListCmsMenus;
use App\Filament\Resources\CmsMenuResource\RelationManagers\CmsMenuItemsRelationManager;
use App\Models\CmsMenu;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CmsMenuResource extends Resource
{
    protected static ?string $model = CmsMenu::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';
    protected static ?int $navigationSort = 84;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('cms_menu.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('cms_menu.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cms_menu.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('cms_menu.sections.information'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label(__('cms_menu.fields.name'))
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),

                            TextInput::make('slug')
                                ->label(__('cms_menu.fields.slug'))
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                        ]),

                        TextInput::make('location')
                            ->label(__('cms_menu.fields.location'))
                            ->helperText(__('cms_menu.fields.location_helper'))
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('cms_menu.columns.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('location')
                    ->label(__('cms_menu.columns.location'))
                    ->placeholder('—'),

                TextColumn::make('all_items_count')
                    ->label(__('cms_menu.columns.items_count'))
                    ->counts('allItems')
                    ->badge()
                    ->color('primary'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('cms_menu.actions.create_first')),
            ])
            ->emptyStateHeading(__('cms_menu.empty_state.heading'))
            ->emptyStateDescription(__('cms_menu.empty_state.description'));
    }

    public static function getRelations(): array
    {
        return [
            CmsMenuItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCmsMenus::route('/'),
            'create' => CreateCmsMenu::route('/create'),
            'edit' => EditCmsMenu::route('/{record}/edit'),
        ];
    }
}

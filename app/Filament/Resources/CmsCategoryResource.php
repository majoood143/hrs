<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CmsCategoryResource\Pages\CreateCmsCategory;
use App\Filament\Resources\CmsCategoryResource\Pages\EditCmsCategory;
use App\Filament\Resources\CmsCategoryResource\Pages\ListCmsCategories;
use App\Models\CmsCategory;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CmsCategoryResource extends Resource
{
    protected static ?string $model = CmsCategory::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';
    protected static ?int $navigationSort = 82;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('cms_category.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('cms_category.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cms_category.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('cms_category.sections.information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name.en')
                                    ->label(__('cms_category.fields.name_en'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),

                                TextInput::make('name.ar')
                                    ->label(__('cms_category.fields.name_ar'))
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                            ]),

                        TextInput::make('slug')
                            ->label(__('cms_category.fields.slug'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Grid::make(2)
                            ->schema([
                                Textarea::make('description.en')
                                    ->label(__('cms_category.fields.description_en'))
                                    ->rows(2),

                                Textarea::make('description.ar')
                                    ->label(__('cms_category.fields.description_ar'))
                                    ->rows(2)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('parent_id')
                                    ->label(__('cms_category.fields.parent'))
                                    ->relationship('parent', 'name')
                                    ->getOptionLabelFromRecordUsing(fn (CmsCategory $record) => $record->getTranslation('name', app()->getLocale()))
                                    ->searchable()
                                    ->preload()
                                    ->native(false),

                                TextInput::make('order')
                                    ->label(__('cms_category.fields.order'))
                                    ->numeric()
                                    ->default(0),
                            ]),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('cms_category.columns.name'))
                    ->getStateUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable(['name->en', 'name->ar'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('parent.name')
                    ->label(__('cms_category.columns.parent'))
                    ->getStateUsing(fn ($record) => $record->parent?->getTranslation('name', app()->getLocale()))
                    ->placeholder('—'),

                TextColumn::make('posts_count')
                    ->label(__('cms_category.columns.posts_count'))
                    ->counts('posts')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('order')
                    ->label(__('cms_category.columns.order'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('order')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->before(function (CmsCategory $record) {
                            if (! $record->canBeDeleted()) {
                                Notification::make()
                                    ->danger()
                                    ->title(__('cms_category.notifications.cannot_delete'))
                                    ->body(__('cms_category.notifications.has_posts'))
                                    ->send();

                                return false;
                            }
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('cms_category.actions.create_first')),
            ])
            ->emptyStateHeading(__('cms_category.empty_state.heading'))
            ->emptyStateDescription(__('cms_category.empty_state.description'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCmsCategories::route('/'),
            'create' => CreateCmsCategory::route('/create'),
            'edit' => EditCmsCategory::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Blocks\Cms\CmsBlocks;
use App\Filament\Resources\CmsPostResource\Pages\CreateCmsPost;
use App\Filament\Resources\CmsPostResource\Pages\EditCmsPost;
use App\Filament\Resources\CmsPostResource\Pages\ListCmsPosts;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\SpatieTagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CmsPostResource extends Resource
{
    protected static ?string $model = CmsPost::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';
    protected static ?int $navigationSort = 81;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('cms_post.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('cms_post.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cms_post.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('cms_post_tabs')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('cms_post.tabs.content'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make(__('cms_post.sections.details'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('title.en')
                                                ->label(__('cms_post.fields.title_en'))
                                                ->required()
                                                ->maxLength(255)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),

                                            TextInput::make('title.ar')
                                                ->label(__('cms_post.fields.title_ar'))
                                                ->required()
                                                ->maxLength(255)
                                                ->extraInputAttributes(['dir' => 'rtl']),
                                        ]),

                                        TextInput::make('slug')
                                            ->label(__('cms_post.fields.slug'))
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),

                                        Grid::make(2)->schema([
                                            Textarea::make('excerpt.en')
                                                ->label(__('cms_post.fields.excerpt_en'))
                                                ->rows(2),

                                            Textarea::make('excerpt.ar')
                                                ->label(__('cms_post.fields.excerpt_ar'))
                                                ->rows(2)
                                                ->extraInputAttributes(['dir' => 'rtl']),
                                        ]),

                                        Grid::make(2)->schema([
                                            Select::make('category_id')
                                                ->label(__('cms_post.fields.category'))
                                                ->relationship('category', 'name')
                                                ->getOptionLabelFromRecordUsing(fn (CmsCategory $record) => $record->getTranslation('name', app()->getLocale()))
                                                ->searchable()
                                                ->preload()
                                                ->native(false),

                                            SpatieTagsInput::make('tags')
                                                ->label(__('cms_post.fields.tags')),
                                        ]),
                                    ]),

                                Builder::make('content')
                                    ->label(__('cms_post.fields.content'))
                                    ->blocks(CmsBlocks::all())
                                    ->collapsible()
                                    ->blockNumbers(false),
                            ]),

                        Tab::make(__('cms_post.tabs.media'))
                            ->icon('heroicon-o-photo')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('featured_image')
                                    ->label(__('cms_post.fields.featured_image'))
                                    ->collection('featured_image')
                                    ->disk('public')
                                    ->image(),

                                SpatieMediaLibraryFileUpload::make('gallery')
                                    ->label(__('cms_post.fields.gallery'))
                                    ->collection('gallery')
                                    ->disk('public')
                                    ->image()
                                    ->multiple()
                                    ->reorderable(),
                            ]),

                        Tab::make(__('cms_post.tabs.settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('status')
                                        ->label(__('cms_post.fields.status'))
                                        ->options([
                                            'draft' => __('cms_post.status.draft'),
                                            'published' => __('cms_post.status.published'),
                                            'scheduled' => __('cms_post.status.scheduled'),
                                        ])
                                        ->default('draft')
                                        ->native(false)
                                        ->required()
                                        ->live(),

                                    DateTimePicker::make('published_at')
                                        ->label(__('cms_post.fields.published_at'))
                                        ->visible(fn ($get) => $get('status') === 'scheduled'),
                                ]),

                                TextInput::make('template')
                                    ->label(__('cms_post.fields.template'))
                                    ->default('default')
                                    ->maxLength(255),

                                Section::make(__('cms_post.sections.theme'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('container_width')
                                                ->label(__('cms_post.fields.container_width'))
                                                ->helperText(__('cms_post.fields.container_width_helper'))
                                                ->options([
                                                    'boxed' => __('cms_page.container_width.boxed'),
                                                    'full_width' => __('cms_page.container_width.full_width'),
                                                ])
                                                ->default('boxed')
                                                ->native(false)
                                                ->required(),

                                            \Filament\Forms\Components\Toggle::make('show_title')
                                                ->label(__('cms_post.fields.show_title'))
                                                ->helperText(__('cms_post.fields.show_title_helper'))
                                                ->default(true)
                                                ->inline(false),
                                        ]),

                                        Textarea::make('custom_css')
                                            ->label(__('cms_post.fields.custom_css'))
                                            ->helperText(__('cms_post.fields.custom_css_helper'))
                                            ->rows(6)
                                            ->extraInputAttributes(['class' => 'font-mono text-sm', 'dir' => 'ltr']),
                                    ]),
                            ]),

                        Tab::make(__('cms_post.tabs.seo'))
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('meta_title.en')
                                        ->label(__('cms_post.fields.meta_title_en'))
                                        ->maxLength(255),

                                    TextInput::make('meta_title.ar')
                                        ->label(__('cms_post.fields.meta_title_ar'))
                                        ->maxLength(255)
                                        ->extraInputAttributes(['dir' => 'rtl']),
                                ]),

                                Grid::make(2)->schema([
                                    Textarea::make('meta_description.en')
                                        ->label(__('cms_post.fields.meta_description_en'))
                                        ->rows(2)
                                        ->maxLength(300),

                                    Textarea::make('meta_description.ar')
                                        ->label(__('cms_post.fields.meta_description_ar'))
                                        ->rows(2)
                                        ->maxLength(300)
                                        ->extraInputAttributes(['dir' => 'rtl']),
                                ]),

                                TextInput::make('canonical_url')
                                    ->label(__('cms_post.fields.canonical_url'))
                                    ->url()
                                    ->maxLength(255),

                                SpatieMediaLibraryFileUpload::make('og_image')
                                    ->label(__('cms_post.fields.og_image'))
                                    ->collection('og_image')
                                    ->disk('public')
                                    ->image(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('featured_image')
                    ->label(__('cms_post.columns.featured_image'))
                    ->collection('featured_image')
                    ->conversion('thumb'),

                TextColumn::make('title')
                    ->label(__('cms_post.columns.title'))
                    ->getStateUsing(fn ($record) => $record->getTranslation('title', app()->getLocale()))
                    ->searchable(['title->en', 'title->ar'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('category.name')
                    ->label(__('cms_post.columns.category'))
                    ->getStateUsing(fn ($record) => $record->category?->getTranslation('name', app()->getLocale()))
                    ->placeholder('—'),

                SpatieTagsColumn::make('tags')
                    ->label(__('cms_post.columns.tags')),

                TextColumn::make('status')
                    ->label(__('cms_post.columns.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('published_at')
                    ->label(__('cms_post.columns.published_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('cms_post.filters.status'))
                    ->options([
                        'draft' => __('cms_post.status.draft'),
                        'published' => __('cms_post.status.published'),
                        'scheduled' => __('cms_post.status.scheduled'),
                    ]),

                SelectFilter::make('category_id')
                    ->label(__('cms_post.filters.category'))
                    ->relationship('category', 'name')
                    ->getOptionLabelFromRecordUsing(fn (CmsCategory $record) => $record->getTranslation('name', app()->getLocale()))
                    ->preload(),
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
                    ->label(__('cms_post.actions.create_first')),
            ])
            ->emptyStateHeading(__('cms_post.empty_state.heading'))
            ->emptyStateDescription(__('cms_post.empty_state.description'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCmsPosts::route('/'),
            'create' => CreateCmsPost::route('/create'),
            'edit' => EditCmsPost::route('/{record}/edit'),
        ];
    }
}

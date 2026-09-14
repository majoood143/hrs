<?php

namespace App\Filament\Resources;

use App\Filament\Blocks\Cms\CmsBlocks;
use App\Filament\Resources\CmsPageResource\Pages\CreateCmsPage;
use App\Filament\Resources\CmsPageResource\Pages\EditCmsPage;
use App\Filament\Resources\CmsPageResource\Pages\ListCmsPages;
use App\Models\CmsPage;
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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CmsPageResource extends Resource
{
    protected static ?string $model = CmsPage::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document';
    protected static ?int $navigationSort = 80;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('cms_page.navigation.plural');
    }

    public static function getModelLabel(): string
    {
        return __('cms_page.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cms_page.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('cms_page_tabs')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('cms_page.tabs.content'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make(__('cms_page.sections.details'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('title.en')
                                                ->label(__('cms_page.fields.title_en'))
                                                ->required()
                                                ->maxLength(255)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),

                                            TextInput::make('title.ar')
                                                ->label(__('cms_page.fields.title_ar'))
                                                ->required()
                                                ->maxLength(255)
                                                ->extraInputAttributes(['dir' => 'rtl']),
                                        ]),

                                        TextInput::make('slug')
                                            ->label(__('cms_page.fields.slug'))
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->rule('not_in:admin,transportation,blog')
                                            ->disabled(fn (Get $get) => (bool) $get('is_system'))
                                            ->dehydrated(fn (Get $get) => ! $get('is_system'))
                                            ->helperText(fn (Get $get) => $get('is_system')
                                                ? __('cms_page.fields.slug_system_helper')
                                                : null),

                                        Grid::make(2)->schema([
                                            Textarea::make('excerpt.en')
                                                ->label(__('cms_page.fields.excerpt_en'))
                                                ->rows(2),

                                            Textarea::make('excerpt.ar')
                                                ->label(__('cms_page.fields.excerpt_ar'))
                                                ->rows(2)
                                                ->extraInputAttributes(['dir' => 'rtl']),
                                        ]),
                                    ]),

                                Builder::make('content')
                                    ->label(__('cms_page.fields.content'))
                                    ->helperText(fn (Get $get) => $get('is_system')
                                        ? __('cms_page.fields.content_system_helper')
                                        : null)
                                    ->blocks(CmsBlocks::all())
                                    ->collapsible()
                                    ->blockNumbers(false),
                            ]),

                        Tab::make(__('cms_page.tabs.media'))
                            ->icon('heroicon-o-photo')
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('featured_image')
                                    ->label(__('cms_page.fields.featured_image'))
                                    ->collection('featured_image')
                                    ->disk('public')
                                    ->image(),

                                SpatieMediaLibraryFileUpload::make('gallery')
                                    ->label(__('cms_page.fields.gallery'))
                                    ->collection('gallery')
                                    ->disk('public')
                                    ->image()
                                    ->multiple()
                                    ->reorderable(),
                            ]),

                        Tab::make(__('cms_page.tabs.settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('status')
                                        ->label(__('cms_page.fields.status'))
                                        ->options([
                                            'draft' => __('cms_page.status.draft'),
                                            'published' => __('cms_page.status.published'),
                                            'scheduled' => __('cms_page.status.scheduled'),
                                        ])
                                        ->default('draft')
                                        ->native(false)
                                        ->required()
                                        ->live(),

                                    DateTimePicker::make('published_at')
                                        ->label(__('cms_page.fields.published_at'))
                                        ->visible(fn ($get) => $get('status') === 'scheduled'),
                                ]),

                                Grid::make(2)->schema([
                                    TextInput::make('template')
                                        ->label(__('cms_page.fields.template'))
                                        ->default('default')
                                        ->maxLength(255),

                                    TextInput::make('layout')
                                        ->label(__('cms_page.fields.layout'))
                                        ->maxLength(255),
                                ]),

                                Toggle::make('is_homepage')
                                    ->label(__('cms_page.fields.is_homepage'))
                                    ->helperText(__('cms_page.fields.is_homepage_helper')),

                                Toggle::make('is_system')
                                    ->label(__('cms_page.fields.is_system'))
                                    ->helperText(__('cms_page.fields.is_system_helper'))
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn (Get $get) => (bool) $get('is_system')),

                                Section::make(__('cms_page.sections.theme'))
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('container_width')
                                                ->label(__('cms_page.fields.container_width'))
                                                ->helperText(__('cms_page.fields.container_width_helper'))
                                                ->options([
                                                    'boxed' => __('cms_page.container_width.boxed'),
                                                    'full_width' => __('cms_page.container_width.full_width'),
                                                ])
                                                ->default('boxed')
                                                ->native(false)
                                                ->required(),

                                            Toggle::make('show_title')
                                                ->label(__('cms_page.fields.show_title'))
                                                ->helperText(__('cms_page.fields.show_title_helper'))
                                                ->default(true)
                                                ->inline(false),
                                        ]),

                                        Textarea::make('custom_css')
                                            ->label(__('cms_page.fields.custom_css'))
                                            ->helperText(__('cms_page.fields.custom_css_helper'))
                                            ->rows(6)
                                            ->extraInputAttributes(['class' => 'font-mono text-sm', 'dir' => 'ltr']),

                                        Textarea::make('custom_head_scripts')
                                            ->label(__('cms_page.fields.custom_head_scripts'))
                                            ->helperText(__('cms_page.fields.custom_head_scripts_helper'))
                                            ->rows(6)
                                            ->extraInputAttributes(['class' => 'font-mono text-sm', 'dir' => 'ltr']),

                                        Textarea::make('custom_body_scripts')
                                            ->label(__('cms_page.fields.custom_body_scripts'))
                                            ->helperText(__('cms_page.fields.custom_body_scripts_helper'))
                                            ->rows(6)
                                            ->extraInputAttributes(['class' => 'font-mono text-sm', 'dir' => 'ltr']),
                                    ]),
                            ]),

                        Tab::make(__('cms_page.tabs.seo'))
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('meta_title.en')
                                        ->label(__('cms_page.fields.meta_title_en'))
                                        ->maxLength(255),

                                    TextInput::make('meta_title.ar')
                                        ->label(__('cms_page.fields.meta_title_ar'))
                                        ->maxLength(255)
                                        ->extraInputAttributes(['dir' => 'rtl']),
                                ]),

                                Grid::make(2)->schema([
                                    Textarea::make('meta_description.en')
                                        ->label(__('cms_page.fields.meta_description_en'))
                                        ->rows(2)
                                        ->maxLength(300),

                                    Textarea::make('meta_description.ar')
                                        ->label(__('cms_page.fields.meta_description_ar'))
                                        ->rows(2)
                                        ->maxLength(300)
                                        ->extraInputAttributes(['dir' => 'rtl']),
                                ]),

                                TextInput::make('canonical_url')
                                    ->label(__('cms_page.fields.canonical_url'))
                                    ->url()
                                    ->maxLength(255),

                                Grid::make(3)->schema([
                                    Toggle::make('noindex')
                                        ->label(__('cms_page.fields.noindex')),

                                    Toggle::make('nofollow')
                                        ->label(__('cms_page.fields.nofollow')),

                                    Select::make('og_type')
                                        ->label(__('cms_page.fields.og_type'))
                                        ->options([
                                            'website' => 'website',
                                            'article' => 'article',
                                        ])
                                        ->default('website')
                                        ->native(false),
                                ]),

                                SpatieMediaLibraryFileUpload::make('og_image')
                                    ->label(__('cms_page.fields.og_image'))
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
                    ->label(__('cms_page.columns.featured_image'))
                    ->collection('featured_image')
                    ->conversion('thumb'),

                TextColumn::make('title')
                    ->label(__('cms_page.columns.title'))
                    ->getStateUsing(fn ($record) => $record->getTranslation('title', app()->getLocale()))
                    ->searchable(['title->en', 'title->ar'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('slug')
                    ->label(__('cms_page.columns.slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label(__('cms_page.columns.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('is_homepage')
                    ->label(__('cms_page.columns.is_homepage'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_system')
                    ->label(__('cms_page.columns.is_system'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('published_at')
                    ->label(__('cms_page.columns.published_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('cms_page.filters.status'))
                    ->options([
                        'draft' => __('cms_page.status.draft'),
                        'published' => __('cms_page.status.published'),
                        'scheduled' => __('cms_page.status.scheduled'),
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()
                        ->before(fn (CmsPage $record) => static::guardDelete($record)),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->label(__('cms_page.actions.create_first')),
            ])
            ->emptyStateHeading(__('cms_page.empty_state.heading'))
            ->emptyStateDescription(__('cms_page.empty_state.description'));
    }

    public static function guardDelete(CmsPage $record): bool
    {
        if ($record->canBeDeleted()) {
            return true;
        }

        Notification::make()
            ->danger()
            ->title(__('cms_page.notifications.cannot_delete'))
            ->body($record->is_homepage
                ? __('cms_page.notifications.is_homepage')
                : __('cms_page.notifications.is_system'))
            ->send();

        return false;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCmsPages::route('/'),
            'create' => CreateCmsPage::route('/create'),
            'edit' => EditCmsPage::route('/{record}/edit'),
        ];
    }
}

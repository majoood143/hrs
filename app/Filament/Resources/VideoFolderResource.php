<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoFolderResource\Pages\CreateVideoFolder;
use App\Filament\Resources\VideoFolderResource\Pages\EditVideoFolder;
use App\Filament\Resources\VideoFolderResource\Pages\ListVideoFolders;
use App\Filament\Support\TranslatableInput;
use App\Filament\Support\WebsiteLinkActions;
use App\Models\VideoFolder;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

use function Filament\Support\generate_search_column_expression;
use function Filament\Support\generate_search_term_expression;

class VideoFolderResource extends Resource
{
    protected static ?string $model = VideoFolder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 91;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('video_folders.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('video_folders.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('video_folders.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("name.{$code}")
                    ->label(__('video_folders.fields.name').' ('.$meta['native'].')')
                    ->required($code === TranslatableInput::defaultLocale())
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set) use ($code) {
                        if ($code === 'en') {
                            $set('slug', Str::slug($state));
                        }
                    })),

                TextInput::make('slug')
                    ->label(__('video_folders.fields.slug'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                DatePicker::make('date')
                    ->label(__('video_folders.fields.date'))
                    ->helperText(__('video_folders.fields.date_helper'))
                    ->native(false),

                SpatieMediaLibraryFileUpload::make('card_image')
                    ->label(__('video_folders.fields.card_image'))
                    ->helperText(__('video_folders.fields.card_image_helper'))
                    ->collection('card_image')
                    ->disk('public')
                    ->image(),

                Select::make('parent_id')
                    ->label(__('video_folders.fields.parent'))
                    ->helperText(__('video_folders.fields.parent_helper'))
                    ->options(function (Get $get, $record) {
                        $excludeIds = $record ? $record->selfAndDescendantIds() : [];

                        return VideoFolder::query()
                            ->when($excludeIds, fn ($query) => $query->whereNotIn('id', $excludeIds))
                            ->ordered()
                            ->get()
                            ->mapWithKeys(fn (VideoFolder $folder) => [$folder->id => $folder->path_label]);
                    })
                    ->native(false)
                    ->searchable()
                    ->preload(),

                TextInput::make('order')
                    ->label(__('video_folders.fields.order'))
                    ->helperText(__('video_folders.fields.order_helper'))
                    ->numeric()
                    ->default(0),

                Toggle::make('is_active')
                    ->label(__('video_folders.fields.is_active'))
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('card_image')
                    ->label(__('video_folders.fields.card_image'))
                    ->collection('card_image')
                    ->square(),

                TextColumn::make('name')
                    ->label(__('video_folders.fields.name'))
                    ->getStateUsing(fn (VideoFolder $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable(['name->en', 'name->ar'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('parent.name')
                    ->label(__('video_folders.fields.parent'))
                    ->getStateUsing(fn (VideoFolder $record) => $record->parent?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => static::whereParentNameLike($query, $search))
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('date')
                    ->label(__('video_folders.fields.date'))
                    ->date()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('order')
                    ->label(__('video_folders.fields.order'))
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label(__('video_folders.fields.is_active'))
                    ->disabled(fn (VideoFolder $record) => ! static::canEdit($record))
                    ->sortable(),

                TextColumn::make('videos_count')
                    ->label(__('video_folders.navigation.videos'))
                    ->counts('videos'),

                TextColumn::make('children_count')
                    ->label(__('video_folders.navigation.subfolders'))
                    ->counts('children'),
            ])
            ->defaultSort('order')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('parent'))
            ->filters([
                SelectFilter::make('parent_id')
                    ->label(__('video_folders.filters.parent'))
                    ->options(fn () => VideoFolder::query()
                        ->whereHas('children')
                        ->with('parent.parent.parent')
                        ->ordered()
                        ->get()
                        ->mapWithKeys(fn (VideoFolder $folder) => [$folder->id => $folder->path_label]))
                    ->multiple()
                    ->searchable(),

                TernaryFilter::make('level')
                    ->label(__('video_folders.filters.level'))
                    ->placeholder(__('video_folders.filters.level_all'))
                    ->trueLabel(__('video_folders.filters.level_top'))
                    ->falseLabel(__('video_folders.filters.level_sub'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('parent_id'),
                        false: fn (Builder $query) => $query->whereNotNull('parent_id'),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('is_active')
                    ->label(__('video_folders.fields.is_active')),

                TernaryFilter::make('has_videos')
                    ->label(__('video_folders.filters.has_videos'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('videos'),
                        false: fn (Builder $query) => $query->whereDoesntHave('videos'),
                        blank: fn (Builder $query) => $query,
                    ),

                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('video_folders.filters.date_from'))
                            ->native(false),
                        DatePicker::make('until')
                            ->label(__('video_folders.filters.date_until'))
                            ->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('date', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = __('video_folders.filters.date_from').': '.Carbon::parse($data['from'])->toFormattedDateString();
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = __('video_folders.filters.date_until').': '.Carbon::parse($data['until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    ...WebsiteLinkActions::make(
                        url: fn (VideoFolder $record): string => route('video-library.folder', $record->slug),
                        isPublic: fn (VideoFolder $record): bool => $record->is_active && filled($record->slug),
                    ),
                    EditAction::make(),
                    DeleteAction::make()
                        ->disabled(fn (VideoFolder $record) => $record->videos()->exists() || $record->children()->exists()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('video_folders.empty_state.heading'))
            ->emptyStateDescription(__('video_folders.empty_state.description'));
    }

    /**
     * Folders whose parent's English or Arabic name contains the search term.
     */
    protected static function whereParentNameLike(Builder $query, string $search): Builder
    {
        $connection = $query->getConnection();
        $term = '%'.generate_search_term_expression($search, null, $connection).'%';

        return $query->whereHas('parent', fn (Builder $parent) => $parent
            ->where(generate_search_column_expression('name->en', null, $connection), 'like', $term)
            ->orWhere(generate_search_column_expression('name->ar', null, $connection), 'like', $term));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideoFolders::route('/'),
            'create' => CreateVideoFolder::route('/create'),
            'edit' => EditVideoFolder::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoResource\Pages\CreateVideo;
use App\Filament\Resources\VideoResource\Pages\EditVideo;
use App\Filament\Resources\VideoResource\Pages\ListVideos;
use App\Filament\Support\TranslatableInput;
use App\Models\Video;
use App\Models\VideoFolder;
use App\Support\YouTube;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VideoResource extends Resource
{
    protected static ?string $model = Video::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-play-circle';
    protected static ?int $navigationSort = 92;

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getModelLabel(): string
    {
        return __('videos.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('videos.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('videos.navigation.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('folder_id')
                    ->label(__('videos.fields.folder'))
                    ->relationship('folder', 'id')
                    ->getOptionLabelFromRecordUsing(fn (VideoFolder $record) => $record->path_label)
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->required(),

                TranslatableInput::grid(fn ($code, $meta) => TextInput::make("title.{$code}")
                    ->label(__('videos.fields.title') . ' (' . $meta['native'] . ')')
                    ->required($code === TranslatableInput::defaultLocale())
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set) use ($code) {
                        if ($code === 'en') {
                            $set('slug', Str::slug($state));
                        }
                    })),

                TextInput::make('slug')
                    ->label(__('videos.fields.slug'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TranslatableInput::grid(fn ($code, $meta) => Textarea::make("description.{$code}")
                    ->label(__('videos.fields.description') . ' (' . $meta['native'] . ')')
                    ->rows(4)),

                TextInput::make('youtube_url')
                    ->label(__('videos.fields.youtube_url'))
                    ->helperText(__('videos.fields.youtube_url_helper'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->rules([
                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                            if (filled($value) && ! YouTube::videoId($value)) {
                                $fail(__('videos.validation.youtube_url'));
                            }
                        },
                    ]),

                Grid::make(2)->schema([
                    TextInput::make('order')
                        ->label(__('videos.fields.order'))
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_active')
                        ->label(__('videos.fields.is_active'))
                        ->default(true),
                ]),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('folder.parent'))
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label(''),

                TextColumn::make('title')
                    ->label(__('videos.fields.title'))
                    ->getStateUsing(fn (Video $record) => $record->getTranslation('title', app()->getLocale()))
                    ->searchable(['title->en', 'title->ar'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('folder.name')
                    ->label(__('videos.fields.folder'))
                    ->getStateUsing(fn (Video $record) => $record->folder?->rootAncestor()->name)
                    ->description(fn (Video $record) => $record->folder?->sub_path_label)
                    ->badge(),

                TextColumn::make('order')
                    ->label(__('videos.fields.order'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('videos.fields.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('order')
            ->filters([
                SelectFilter::make('folder_id')
                    ->label(__('videos.filters.folder'))
                    ->relationship('folder', 'id')
                    ->getOptionLabelFromRecordUsing(fn (VideoFolder $record) => $record->path_label),
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
            ->emptyStateHeading(__('videos.empty_state.heading'))
            ->emptyStateDescription(__('videos.empty_state.description'));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVideos::route('/'),
            'create' => CreateVideo::route('/create'),
            'edit' => EditVideo::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoFolderResource\Pages\CreateVideoFolder;
use App\Filament\Resources\VideoFolderResource\Pages\EditVideoFolder;
use App\Filament\Resources\VideoFolderResource\Pages\ListVideoFolders;
use App\Filament\Support\TranslatableInput;
use App\Models\VideoFolder;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

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
                    ->label(__('video_folders.fields.name') . ' (' . $meta['native'] . ')')
                    ->required($code === TranslatableInput::defaultLocale())
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, $set) use ($code) {
                        if ($code === TranslatableInput::defaultLocale()) {
                            $set('slug', Str::slug($state));
                        }
                    })),

                TextInput::make('slug')
                    ->label(__('video_folders.fields.slug'))
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

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
                    ->badge()
                    ->placeholder('—'),

                TextColumn::make('order')
                    ->label(__('video_folders.fields.order'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('video_folders.fields.is_active'))
                    ->boolean(),

                TextColumn::make('videos_count')
                    ->label(__('video_folders.navigation.videos'))
                    ->counts('videos'),

                TextColumn::make('children_count')
                    ->label(__('video_folders.navigation.subfolders'))
                    ->counts('children'),
            ])
            ->defaultSort('order')
            ->recordActions([
                ActionGroup::make([
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

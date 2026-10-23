<?php

namespace App\Filament\Resources;

use App\Filament\Resources\YouTubeChannelResource\Pages\CreateYouTubeChannel;
use App\Filament\Resources\YouTubeChannelResource\Pages\EditYouTubeChannel;
use App\Filament\Resources\YouTubeChannelResource\Pages\ListYouTubeChannels;
use App\Models\YouTubeChannel;
use App\Models\YouTubeChannelVideo;
use App\Services\YouTube\ChannelWatcher;
use App\Services\YouTube\YouTubeClient;
use App\Services\YouTube\YouTubeException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** Watched YouTube channels (not ours): new videos go to the YouTube inbox + a WhatsApp alert. */
class YouTubeChannelResource extends Resource
{
    protected static ?string $model = YouTubeChannel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static ?int $navigationSort = 93;

    protected static ?string $slug = 'youtube-channels';

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_youtube.channels.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_youtube.channels.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_youtube.channels.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->description(fn () => __('admin_youtube.channels.intro')
                    .(app(YouTubeClient::class)->hasKey() ? '' : ' '.__('admin_youtube.no_key')))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('url')
                        ->label(__('admin_youtube.fields.url'))
                        ->helperText(__('admin_youtube.fields.url_helper'))
                        ->placeholder('https://www.youtube.com/@name')
                        ->required()
                        ->maxLength(255)
                        ->extraInputAttributes(['dir' => 'ltr'])
                        ->visibleOn('create')
                        ->columnSpanFull(),

                    TextInput::make('title')
                        ->label(__('admin_youtube.fields.channel'))
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit'),

                    TextInput::make('channel_id')
                        ->label(__('admin_youtube.fields.channel_id'))
                        ->disabled()
                        ->dehydrated(false)
                        ->extraInputAttributes(['dir' => 'ltr'])
                        ->visibleOn('edit'),

                    Select::make('video_folder_id')
                        ->label(__('admin_youtube.fields.folder'))
                        ->helperText(__('admin_youtube.fields.folder_helper'))
                        ->options(fn () => VideoResource::folderOptions())
                        ->native(false)
                        ->searchable()
                        // auto-added videos go in it
                        ->required(fn (Get $get) => ($get('auto_add') ?? YouTubeChannel::AUTO_ADD_OFF) !== YouTubeChannel::AUTO_ADD_OFF)
                        ->columnSpanFull(),

                    Toggle::make('skip_shorts')
                        ->label(__('admin_youtube.fields.skip_shorts'))
                        ->helperText(__('admin_youtube.fields.skip_shorts_helper'))
                        ->default(true),

                    Toggle::make('is_active')
                        ->label(__('admin_youtube.fields.is_active'))
                        ->default(true),
                ]),

            Section::make(__('admin_youtube.automation.heading'))
                ->description(__('admin_youtube.automation.description'))
                ->icon('heroicon-o-bolt')
                ->columnSpanFull()
                ->schema([
                    ToggleButtons::make('auto_add')
                        ->label(__('admin_youtube.fields.auto_add'))
                        ->helperText(__('admin_youtube.fields.auto_add_helper'))
                        ->options(collect(YouTubeChannel::AUTO_ADD_MODES)->mapWithKeys(fn (string $mode) => [$mode => __('admin_youtube.auto_add.'.$mode)]))
                        ->icons([
                            YouTubeChannel::AUTO_ADD_OFF => 'heroicon-o-inbox-arrow-down',
                            YouTubeChannel::AUTO_ADD_HIDDEN => 'heroicon-o-eye-slash',
                            YouTubeChannel::AUTO_ADD_PUBLISH => 'heroicon-o-check-circle',
                        ])
                        ->colors([
                            YouTubeChannel::AUTO_ADD_OFF => 'gray',
                            YouTubeChannel::AUTO_ADD_HIDDEN => 'warning',
                            YouTubeChannel::AUTO_ADD_PUBLISH => 'success',
                        ])
                        ->inline()
                        ->required()
                        ->default(YouTubeChannel::AUTO_ADD_OFF)
                        ->live(),

                    Toggle::make('notify')
                        ->label(__('admin_youtube.fields.notify'))
                        ->helperText(__('admin_youtube.fields.notify_helper'))
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with('folder')
                ->withCount(['videos as waiting_count' => fn ($q) => $q->where('status', YouTubeChannelVideo::STATUS_NEW)]))
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label('')
                    ->circular(),

                TextColumn::make('title')
                    ->label(__('admin_youtube.fields.channel'))
                    ->description(fn (YouTubeChannel $record) => $record->handle)
                    ->searchable(['title', 'handle', 'channel_id'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('folder.name')
                    ->label(__('admin_youtube.fields.folder'))
                    ->getStateUsing(fn (YouTubeChannel $record) => $record->folder?->path_label)
                    ->badge()
                    ->color('gray')
                    ->description(fn (YouTubeChannel $record) => $record->autoAdds() ? __('admin_youtube.auto_add.'.$record->auto_add) : null),

                TextColumn::make('waiting_count')
                    ->label(__('admin_youtube.fields.waiting'))
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray')
                    ->url(fn (YouTubeChannel $record) => YouTubeChannelVideoResource::getUrl('index', [
                        'filters' => ['youtube_channel_id' => ['value' => $record->getKey()]],
                    ])),

                TextColumn::make('checked_at')
                    ->label(__('admin_youtube.fields.checked_at'))
                    ->since()
                    ->placeholder(__('admin_youtube.fields.never_checked'))
                    ->description(fn (YouTubeChannel $record) => YouTubeException::label($record->last_error))
                    ->icon(fn (YouTubeChannel $record) => $record->last_error ? 'heroicon-o-exclamation-triangle' : null)
                    ->iconColor('danger')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label(__('admin_youtube.fields.is_active'))
                    ->disabled(fn (YouTubeChannel $record) => ! static::canEdit($record)),
            ])
            ->defaultSort('title')
            ->recordActions([
                ActionGroup::make([
                    static::checkAction(),
                    Action::make('open_youtube')
                        ->label(__('admin_youtube.actions.open_youtube'))
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (YouTubeChannel $record) => $record->url(), shouldOpenInNewTab: true),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-signal')
            ->emptyStateHeading(__('admin_youtube.channels.empty_heading'))
            ->emptyStateDescription(__('admin_youtube.channels.empty_description'));
    }

    public static function checkAction(): Action
    {
        return Action::make('check_now')
            ->label(__('admin_youtube.actions.check_now'))
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (YouTubeChannel $record) => static::canEdit($record))
            ->action(function (YouTubeChannel $record) {
                $result = app(ChannelWatcher::class)->check($record);
                Notification::make()
                    ->title(__(! $result['checked'] ? 'admin_youtube.notifications.check_failed' : 'admin_youtube.notifications.checked', ['channel' => $record->title]))
                    ->body(collect([
                        __('admin_youtube.notifications.checked_body', ['found' => $result['found'], 'alerted' => $result['alerted']]),
                        $result['added'] > 0 ? __('admin_youtube.notifications.auto_added_body', ['count' => $result['added']]) : null,
                        YouTubeException::label($result['error']),
                    ])->filter()->implode(' '))
                    ->{$result['error'] ? 'warning' : 'success'}()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListYouTubeChannels::route('/'),
            'create' => CreateYouTubeChannel::route('/create'),
            'edit' => EditYouTubeChannel::route('/{record}/edit'),
        ];
    }
}

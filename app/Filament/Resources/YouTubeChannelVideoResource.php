<?php

namespace App\Filament\Resources;

use App\Filament\Resources\YouTubeChannelVideoResource\Pages\ListYouTubeChannelVideos;
use App\Models\YouTubeChannelVideo;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * The YouTube inbox: videos found on the watched channels. Nothing is published from here: "Add
 * to library" opens the normal New video form filled in from YouTube (CreateVideo, ?youtube=).
 */
class YouTubeChannelVideoResource extends Resource
{
    protected static ?string $model = YouTubeChannelVideo::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?int $navigationSort = 94;

    protected static ?string $slug = 'youtube-inbox';

    public static function getNavigationGroup(): ?string
    {
        return __('cms.navigation.group');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_youtube.inbox.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_youtube.inbox.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_youtube.inbox.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        $waiting = YouTubeChannelVideo::query()->waiting()->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('channel'))
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label('')
                    ->imageWidth(96)
                    ->imageHeight(54),

                TextColumn::make('title')
                    ->label(__('admin_youtube.fields.title'))
                    ->description(fn (YouTubeChannelVideo $record) => $record->channel?->title)
                    ->searchable()
                    ->wrap()
                    ->lineClamp(2)
                    ->weight('bold'),

                TextColumn::make('kind')
                    ->label(__('admin_youtube.fields.kind'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin_youtube.kinds.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        'live' => 'danger',
                        'upcoming' => 'warning',
                        'stream' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'live', 'stream' => 'heroicon-o-signal',
                        'upcoming' => 'heroicon-o-calendar',
                        'short' => 'heroicon-o-device-phone-mobile',
                        default => 'heroicon-o-play',
                    })
                    ->description(fn (YouTubeChannelVideo $record) => $record->durationLabel()),

                TextColumn::make('published_at')
                    ->label(__('admin_youtube.fields.published_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->description(fn (YouTubeChannelVideo $record) => $record->kind === 'upcoming' && $record->scheduled_at
                        ? __('admin_youtube.fields.scheduled_at').': '.$record->scheduled_at->timezone(config('stable_bookings.timezone', 'Asia/Muscat'))->format('Y-m-d H:i')
                        : null)
                    ->sortable(),

                IconColumn::make('embeddable')
                    ->label(__('admin_youtube.fields.embeddable'))
                    ->boolean()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('admin_youtube.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin_youtube.statuses.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        YouTubeChannelVideo::STATUS_NEW => 'warning',
                        YouTubeChannelVideo::STATUS_ADDED => 'success',
                        default => 'gray',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        YouTubeChannelVideo::STATUS_NEW => 'heroicon-o-sparkles',
                        YouTubeChannelVideo::STATUS_ADDED => 'heroicon-o-check-circle',
                        default => 'heroicon-o-eye-slash',
                    }),

                TextColumn::make('notified_at')
                    ->label(__('admin_youtube.fields.notified_at'))
                    ->since()
                    ->dateTimeTooltip()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin_youtube.fields.status'))
                    ->options(collect(YouTubeChannelVideo::STATUSES)->mapWithKeys(fn (string $s) => [$s => __('admin_youtube.statuses.'.$s)]))
                    ->default(YouTubeChannelVideo::STATUS_NEW),

                SelectFilter::make('youtube_channel_id')
                    ->label(__('admin_youtube.fields.channel'))
                    ->relationship('channel', 'title')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('kind')
                    ->label(__('admin_youtube.fields.kind'))
                    ->options(collect(YouTubeChannelVideo::KINDS)->mapWithKeys(fn (string $k) => [$k => __('admin_youtube.kinds.'.$k)])),
            ])
            ->recordUrl(null)
            ->recordActions([
                Action::make('add_to_library')
                    ->label(__('admin_youtube.actions.add_to_library'))
                    ->icon('heroicon-o-plus-circle')
                    ->button()
                    ->size('sm')
                    ->visible(fn (YouTubeChannelVideo $record) => $record->status !== YouTubeChannelVideo::STATUS_ADDED && VideoResource::canCreate())
                    ->url(fn (YouTubeChannelVideo $record) => VideoResource::getUrl('create', ['youtube' => $record->getKey()])),

                Action::make('open_in_library')
                    ->label(__('admin_youtube.actions.open_in_library'))
                    ->icon('heroicon-o-film')
                    ->color('gray')
                    ->visible(fn (YouTubeChannelVideo $record) => $record->library_video_id !== null && $record->libraryVideo !== null)
                    ->url(fn (YouTubeChannelVideo $record) => VideoResource::getUrl('edit', ['record' => $record->libraryVideo])),

                ActionGroup::make([
                    Action::make('open_youtube')
                        ->label(__('admin_youtube.actions.open_youtube'))
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->url(fn (YouTubeChannelVideo $record) => $record->watchUrl(), shouldOpenInNewTab: true),

                    Action::make('ignore')
                        ->label(__('admin_youtube.actions.ignore'))
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->visible(fn (YouTubeChannelVideo $record) => $record->status === YouTubeChannelVideo::STATUS_NEW && static::canEdit($record))
                        ->action(function (YouTubeChannelVideo $record) {
                            $record->update(['status' => YouTubeChannelVideo::STATUS_IGNORED]);
                            Notification::make()->title(__('admin_youtube.notifications.ignored'))->success()->send();
                        }),

                    Action::make('restore')
                        ->label(__('admin_youtube.actions.restore'))
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('gray')
                        ->visible(fn (YouTubeChannelVideo $record) => in_array($record->status, [YouTubeChannelVideo::STATUS_IGNORED, YouTubeChannelVideo::STATUS_SKIPPED], true) && static::canEdit($record))
                        ->action(function (YouTubeChannelVideo $record) {
                            $record->update(['status' => YouTubeChannelVideo::STATUS_NEW]);
                            Notification::make()->title(__('admin_youtube.notifications.restored'))->success()->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('ignore')
                        ->label(__('admin_youtube.actions.ignore'))
                        ->icon('heroicon-o-eye-slash')
                        ->authorizeIndividualRecords('update')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $records->where('status', YouTubeChannelVideo::STATUS_NEW)
                                ->each(fn (YouTubeChannelVideo $record) => $record->update(['status' => YouTubeChannelVideo::STATUS_IGNORED]));
                            Notification::make()->title(__('admin_youtube.notifications.ignored'))->success()->send();
                        }),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-inbox')
            ->emptyStateHeading(__('admin_youtube.inbox.empty_heading'))
            ->emptyStateDescription(__('admin_youtube.inbox.empty_description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListYouTubeChannelVideos::route('/'),
        ];
    }
}

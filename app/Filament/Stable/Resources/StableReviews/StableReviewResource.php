<?php

namespace App\Filament\Stable\Resources\StableReviews;

use App\Filament\Stable\Resources\StableReviews\Pages\ListStableReviews;
use App\Models\StableReview;
use App\Services\Stables\StableReviews;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** What customers said about their sessions; the stable can reply (shown under the review). */
class StableReviewResource extends Resource
{
    protected static ?string $model = StableReview::class;

    protected static ?string $tenantRelationshipName = 'reviews';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.bookings');
    }

    public static function getModelLabel(): string
    {
        return __('stable_reviews.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stable_reviews.plural');
    }

    /** Reviews still waiting for a reply. */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereNull('reply')->where('is_visible', true)->count();

        return $count ? (string) $count : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['offering', 'booking']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('rating')
                    ->label(__('stable_reviews.rating'))
                    ->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('comment')
                    ->label(__('stable_reviews.comment'))
                    ->description(fn (StableReview $record) => $record->displayName().' · '.$record->offering?->name.' · '.$record->created_at->format('Y-m-d'))
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('reply')
                    ->label(__('stable_reviews.your_reply'))
                    ->wrap()
                    ->placeholder(__('stable_reviews.no_reply')),
                IconColumn::make('is_visible')->label(__('stable_reviews.visible'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('rating')->label(__('stable_reviews.rating'))->options(collect([5, 4, 3, 2, 1])->mapWithKeys(fn (int $r) => [$r => str_repeat('★', $r)])),
            ])
            ->recordActions([
                Action::make('reply')
                    ->label(fn (StableReview $record) => $record->reply ? __('stable_reviews.edit_reply') : __('stable_reviews.reply'))
                    ->icon('heroicon-o-chat-bubble-left')
                    ->fillForm(fn (StableReview $record) => ['reply' => $record->reply])
                    ->schema([
                        Textarea::make('reply')
                            ->label(__('stable_reviews.your_reply'))
                            ->helperText(__('stable_reviews.reply_hint'))
                            ->rows(4)
                            ->maxLength(1000),
                    ])
                    ->action(function (StableReview $record, array $data): void {
                        app(StableReviews::class)->reply($record, $data['reply'] ?? null);
                        Notification::make()->success()->title(__('stable_reviews.reply_saved'))->send();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-star')
            ->emptyStateHeading(__('stable_reviews.none'));
    }

    public static function getPages(): array
    {
        return ['index' => ListStableReviews::route('/')];
    }
}

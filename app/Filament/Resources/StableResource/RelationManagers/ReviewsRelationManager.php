<?php

namespace App\Filament\Resources\StableResource\RelationManagers;

use App\Models\StableReview;
use App\Services\Stables\StableReviews;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** The stable's reviews; an admin can hide one that should not be public (and show it again). */
class ReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'reviews';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('stable_reviews.plural');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('rating')->label(__('stable_reviews.rating'))->formatStateUsing(fn (int $state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))->color('warning'),
                TextColumn::make('comment')
                    ->label(__('stable_reviews.comment'))
                    ->description(fn (StableReview $record) => $record->author_name.' · '.$record->created_at->format('Y-m-d'))
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('reply')->label(__('stable_reviews.stable_reply'))->wrap()->placeholder('—'),
                IconColumn::make('is_visible')->label(__('stable_reviews.visible'))->boolean(),
            ])
            ->recordActions([
                Action::make('toggle')
                    ->label(fn (StableReview $record) => $record->is_visible ? __('stable_reviews.hide') : __('stable_reviews.show'))
                    ->icon(fn (StableReview $record) => $record->is_visible ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (StableReview $record) => $record->is_visible ? 'danger' : 'gray')
                    ->requiresConfirmation()
                    ->visible(fn () => Gate::allows('update', $this->getOwnerRecord()))
                    ->action(fn (StableReview $record) => app(StableReviews::class)->setVisible($record, ! $record->is_visible)),
            ])
            ->emptyStateHeading(__('stable_reviews.none'));
    }
}

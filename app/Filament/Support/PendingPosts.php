<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Posts waiting for approval (status `pending`: posted from the app while admin → Mobile app →
 * Posting → "Review app posts" is on) in the visitor-post resources (horses and tools for sale,
 * farriers, transfer board): the Approve actions and the navigation badge. Approving = `active`,
 * so the post shows on the website and in the app. Needs the admin's `update` permission.
 */
final class PendingPosts
{
    public const STATUS = 'pending';

    /** The row/page action, shown only on a pending post. */
    public static function approveAction(): Action
    {
        return Action::make('approvePost')
            ->label(__('admin_pending_posts.approve'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->visible(fn (Model $record): bool => $record->getAttribute('status') === self::STATUS && Gate::allows('update', $record))
            ->requiresConfirmation()
            ->modalHeading(__('admin_pending_posts.approve_heading'))
            ->modalDescription(__('admin_pending_posts.approve_description'))
            ->action(function (Model $record): void {
                $record->update(['status' => 'active']);

                Notification::make()->success()->title(__('admin_pending_posts.approved', ['count' => 1]))->send();
            });
    }

    /** Approves the pending posts among the selected ones (the others are left as they are). */
    public static function approveBulkAction(): BulkAction
    {
        return BulkAction::make('approvePosts')
            ->label(__('admin_pending_posts.approve_selected'))
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('admin_pending_posts.approve_description'))
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $approved = 0;

                foreach ($records as $record) {
                    if ($record->getAttribute('status') === self::STATUS && Gate::allows('update', $record)) {
                        $record->update(['status' => 'active']);
                        $approved++;
                    }
                }

                Notification::make()->success()->title(__('admin_pending_posts.approved', ['count' => $approved]))->send();
            });
    }

    /**
     * The menu badge: the posts waiting for approval when there are any (amber), else the
     * active posts as before.
     *
     * @param  class-string<Model>  $model
     */
    public static function navigationBadge(string $model): string
    {
        $pending = $model::query()->where('status', self::STATUS)->count();

        return (string) ($pending > 0 ? $pending : $model::query()->where('status', 'active')->count());
    }

    /** @param  class-string<Model>  $model */
    public static function navigationBadgeColor(string $model): ?string
    {
        return self::hasPending($model) ? 'warning' : null;
    }

    /** @param  class-string<Model>  $model */
    public static function navigationBadgeTooltip(string $model): ?string
    {
        return self::hasPending($model) ? __('admin_pending_posts.badge_tooltip') : null;
    }

    /** @param  class-string<Model>  $model */
    private static function hasPending(string $model): bool
    {
        return $model::query()->where('status', self::STATUS)->exists();
    }
}

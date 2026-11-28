<?php

namespace App\Filament\Resources\GameAttemptResource;

use App\Models\GameAttempt;
use App\Models\GameCampaign;
use App\Services\Games\LeaderboardService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/** The review of a race (admin → Games → Races, and the campaign's Races tab): clear it, throw it out, block the player. */
final class AttemptActions
{
    /** @return list<Action> */
    public static function all(): array
    {
        return [self::clear(), self::invalidate(), self::block(), self::unblock()];
    }

    public static function clear(): Action
    {
        return Action::make('clear')->label(__('games.admin.clear'))->icon('heroicon-o-check-circle')->color('success')
            ->visible(fn (GameAttempt $record) => self::mayUpdate($record) && $record->status === GameAttempt::STATUS_COMPLETED && $record->review_status !== GameAttempt::REVIEW_CLEARED)
            ->schema([self::note()])
            ->action(fn (GameAttempt $record, array $data) => self::review($record, GameAttempt::REVIEW_CLEARED, $data, __('games.admin.cleared')));
    }

    public static function invalidate(): Action
    {
        return Action::make('invalidate')->label(__('games.admin.invalidate'))->icon('heroicon-o-x-circle')->color('danger')
            ->visible(fn (GameAttempt $record) => self::mayUpdate($record) && $record->status === GameAttempt::STATUS_COMPLETED && $record->review_status !== GameAttempt::REVIEW_INVALIDATED)
            ->schema([self::note()])
            ->action(fn (GameAttempt $record, array $data) => self::review($record, GameAttempt::REVIEW_INVALIDATED, $data, __('games.admin.invalidated')));
    }

    public static function block(): Action
    {
        return Action::make('block')->label(__('games.admin.block'))->icon('heroicon-o-no-symbol')->color('danger')
            ->visible(fn (GameAttempt $record) => self::mayUpdate($record) && $record->customer?->games_blocked_at === null)
            ->schema([TextInput::make('reason')->label(__('games.admin.fields.blocked_reason'))->placeholder(__('games.admin.placeholders.blocked_reason'))->maxLength(255)])
            ->action(function (GameAttempt $record, array $data) {
                $record->customer->forceFill(['games_blocked_at' => now(), 'games_blocked_reason' => $data['reason'] ?? null])->save();
                self::forgetBoards($record);
                Notification::make()->success()->title(__('games.admin.blocked_done'))->send();
            });
    }

    public static function unblock(): Action
    {
        return Action::make('unblock')->label(__('games.admin.unblock'))->icon('heroicon-o-arrow-uturn-left')->color('gray')
            ->requiresConfirmation()
            ->visible(fn (GameAttempt $record) => self::mayUpdate($record) && $record->customer?->games_blocked_at !== null)
            ->action(function (GameAttempt $record) {
                $record->customer->forceFill(['games_blocked_at' => null, 'games_blocked_reason' => null])->save();
                self::forgetBoards($record);
                Notification::make()->success()->title(__('games.admin.unblocked'))->send();
            });
    }

    private static function note(): TextInput
    {
        return TextInput::make('note')->label(__('games.admin.fields.review_note'))->placeholder(__('games.admin.placeholders.review_note'))->maxLength(255);
    }

    /** @param  array<string, mixed>  $data */
    private static function review(GameAttempt $attempt, string $status, array $data, string $done): void
    {
        $attempt->forceFill([
            'review_status' => $status,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'review_note' => $data['note'] ?? null,
        ])->save();

        app(LeaderboardService::class)->forget($attempt->campaign);
        Notification::make()->success()->title($done)->send();
    }

    /** A blocked player leaves every board they are on. */
    private static function forgetBoards(GameAttempt $attempt): void
    {
        GameCampaign::query()->whereIn('id', GameAttempt::query()->where('customer_id', $attempt->customer_id)->select('game_campaign_id'))->get()
            ->each(fn (GameCampaign $campaign) => app(LeaderboardService::class)->forget($campaign));
    }

    private static function mayUpdate(GameAttempt $attempt): bool
    {
        return (bool) Auth::user()?->can('update', $attempt);
    }
}

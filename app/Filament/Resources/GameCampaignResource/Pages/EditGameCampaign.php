<?php

namespace App\Filament\Resources\GameCampaignResource\Pages;

use App\Filament\Resources\GameCampaignResource;
use App\Models\GameAttempt;
use App\Models\GameCampaign;
use App\Models\User;
use App\Services\Games\GameAwardService;
use App\Services\Games\LeaderboardService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditGameCampaign extends EditRecord
{
    protected static string $resource = GameCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('game')->label(__('games.admin.open_game'))->icon('heroicon-o-play')->color('gray')
                ->url(fn (GameCampaign $record) => $record->gameUrl())->openUrlInNewTab(),

            // the live board (never the frozen public one), as the prizes will be given
            Action::make('leaderboard')->label(__('games.admin.leaderboard'))->icon('heroicon-o-chart-bar')->color('gray')
                ->modalHeading(__('games.admin.leaderboard'))
                ->modalContent(fn (GameCampaign $record) => view('filament.games.leaderboard', [
                    'rows' => app(LeaderboardService::class)->top($record, max(10, (int) $record->prizes()->max('rank'), $record->leaderboard_size)),
                    'prizes' => $record->prizes()->get()->keyBy('rank'),
                ]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('games.site.close')),

            Action::make('finalize')->label(__('games.admin.finalize'))->icon('heroicon-o-gift')->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn (GameCampaign $record) => app(GameAwardService::class)->blocker($record) ?? __('games.admin.finalize_confirm'))
                ->visible(fn (GameCampaign $record) => ! $record->isAwarded() && Auth::user()?->can('update', $record))
                ->action(function (GameCampaign $record) {
                    if ($reason = app(GameAwardService::class)->blocker($record)) {
                        Notification::make()->danger()->title(__('games.admin.cannot_finalize'))->body($reason)->send();

                        return;
                    }

                    $awards = app(GameAwardService::class)->finalize($record, Auth::user() instanceof User ? Auth::user() : null);
                    Notification::make()->success()->title(__('games.admin.finalized', ['count' => count($awards)]))->send();
                    $this->refreshFormData(['awarded_at']);
                }),

            // races are the campaign's record: archive a campaign that was played
            DeleteAction::make()->hidden(fn (GameCampaign $record) => GameAttempt::query()->where('game_campaign_id', $record->id)->exists()),
        ];
    }
}

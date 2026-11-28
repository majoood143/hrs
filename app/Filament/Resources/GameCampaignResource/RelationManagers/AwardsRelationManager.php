<?php

namespace App\Filament\Resources\GameCampaignResource\RelationManagers;

use App\Models\GameAward;
use App\Services\Games\GameAwardService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Who won what once the prizes were given; tell a winner again or take a prize back. */
class AwardsRelationManager extends RelationManager
{
    protected static string $relationship = 'awards';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('games.admin.awards');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $may = fn () => (bool) Auth::user()?->can('update', $this->getOwnerRecord());

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['customer', 'prize']))
            ->columns([
                TextColumn::make('rank')->label(__('games.admin.fields.rank')),
                TextColumn::make('customer.phone')->label(__('games.admin.fields.phone'))->formatStateUsing(fn (string $state) => "\u{2066}+{$state}\u{2069}")
                    ->description(fn (GameAward $record) => $record->customer?->name),
                TextColumn::make('prize.'.(app()->getLocale() === 'ar' ? 'ar_title' : 'en_title'))->label(__('games.admin.fields.prize')),
                TextColumn::make('status')->label(__('games.admin.fields.status'))->badge()
                    ->formatStateUsing(fn (string $state) => __('games.admin.award_statuses.'.$state))
                    ->color(fn (string $state) => ['notified' => 'success', 'void' => 'danger'][$state] ?? 'warning'),
                TextColumn::make('notified_at')->label(__('games.admin.fields.notified_at'))->dateTime('j M Y, H:i')->placeholder('—'),
                TextColumn::make('notify_error')->label(__('games.admin.fields.notify_error'))->placeholder('—')->wrap(),
            ])
            ->recordActions([
                Action::make('notify')->label(__('games.admin.notify'))->icon('heroicon-o-chat-bubble-left-ellipsis')->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (GameAward $record) => $may() && $record->status !== GameAward::STATUS_VOID)
                    ->action(function (GameAward $record) {
                        app(GameAwardService::class)->notify($record);
                        Notification::make()->success()->title(__('games.admin.notified'))->send();
                    }),
                Action::make('void')->label(__('games.admin.void'))->icon('heroicon-o-x-circle')->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (GameAward $record) => $may() && $record->status !== GameAward::STATUS_VOID)
                    ->action(function (GameAward $record) {
                        app(GameAwardService::class)->void($record);
                        Notification::make()->success()->title(__('games.admin.voided'))->send();
                    }),
            ]);
    }
}

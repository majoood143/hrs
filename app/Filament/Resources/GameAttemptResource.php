<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GameAttemptResource\AttemptActions;
use App\Filament\Resources\GameAttemptResource\Pages\ListGameAttempts;
use App\Filament\Resources\GameAttemptResource\Pages\ViewGameAttempt;
use App\Models\GameAttempt;
use App\Models\GameCampaign;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin → Games → Races: every race of every campaign, with the server's checks. Races waiting for
 * review (the menu badge) stay off the leaderboard until cleared; a cheat is thrown out or blocked.
 * Races are made by the game only, so there is no create or edit.
 */
class GameAttemptResource extends Resource
{
    protected static ?string $model = GameAttempt::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('games.admin.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('games.admin.attempt');
    }

    public static function getPluralModelLabel(): string
    {
        return __('games.admin.attempts');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = GameAttempt::query()->where('review_status', GameAttempt::REVIEW_FLAGGED)->count();

        return $count > 0 ? (string) $count : null;
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
        return self::columns($table, withCampaign: true)
            ->filters([
                SelectFilter::make('game_campaign_id')->label(__('games.admin.campaign'))
                    ->options(fn () => GameCampaign::query()->latest('starts_at')->get()->mapWithKeys(fn (GameCampaign $c) => [$c->id => $c->name])),
                SelectFilter::make('review_status')->label(__('games.admin.fields.review'))->options(fn () => self::options('review_statuses')),
                SelectFilter::make('status')->label(__('games.admin.fields.race_status'))->options(fn () => self::options('attempt_statuses')),
            ])
            ->recordActions([ViewAction::make(), ...AttemptActions::all()]);
    }

    /** The races table, shared with the campaign's Races tab. */
    public static function columns(Table $table, bool $withCampaign): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['customer', 'campaign']))
            ->columns(array_values(array_filter([
                $withCampaign ? TextColumn::make('campaign.'.(app()->getLocale() === 'ar' ? 'ar_name' : 'en_name'))->label(__('games.admin.campaign'))->toggleable() : null,
                TextColumn::make('customer.phone')->label(__('games.admin.fields.phone'))->formatStateUsing(fn (string $state) => "\u{2066}+{$state}\u{2069}")->searchable()
                    ->description(fn (GameAttempt $record) => $record->customer?->name),
                TextColumn::make('status')->label(__('games.admin.fields.race_status'))->badge()
                    ->formatStateUsing(fn (string $state) => __('games.admin.attempt_statuses.'.$state))
                    ->color(fn (string $state) => ['completed' => 'success', 'abandoned' => 'gray'][$state] ?? 'info'),
                TextColumn::make('position')->label(__('games.admin.fields.position'))->sortable()->placeholder('—'),
                TextColumn::make('race_time_ms')->label(__('games.admin.fields.time'))->formatStateUsing(fn (?int $state) => GameAttempt::formatTime($state))->sortable()->placeholder('—'),
                TextColumn::make('risk_score')->label(__('games.admin.fields.risk'))->sortable()->badge()
                    ->color(fn (int $state) => $state >= 60 ? 'danger' : ($state > 0 ? 'warning' : 'gray')),
                TextColumn::make('review_status')->label(__('games.admin.fields.review'))->badge()->placeholder('—')
                    ->formatStateUsing(fn (?string $state) => $state ? __('games.admin.review_statuses.'.$state) : null)
                    ->color(fn (?string $state) => ['flagged' => 'warning', 'cleared' => 'success', 'invalidated' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('started_at')->label(__('games.admin.fields.started_at'))->dateTime('j M Y, H:i')->sortable(),
            ])))
            ->defaultSort('started_at', 'desc');
    }

    public static function infolist(Schema $schema): Schema
    {
        $f = fn (string $key) => __('games.admin.fields.'.$key);

        return $schema->components([
            Section::make(__('games.admin.attempt'))
                ->icon('heroicon-o-flag')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('campaign.name')->label(__('games.admin.campaign')),
                    TextEntry::make('customer.phone')->label($f('phone'))->formatStateUsing(fn (string $state) => "\u{2066}+{$state}\u{2069}"),
                    TextEntry::make('customer.name')->label($f('customer'))->placeholder('—'),
                    TextEntry::make('customer.games_blocked_at')->label($f('blocked'))->dateTime('j M Y, H:i')->placeholder('—')
                        ->helperText(fn (GameAttempt $record) => $record->customer?->games_blocked_reason),
                    TextEntry::make('status')->label($f('race_status'))->badge()->formatStateUsing(fn (string $state) => __('games.admin.attempt_statuses.'.$state)),
                    TextEntry::make('position')->label($f('position'))->formatStateUsing(fn (?int $state, GameAttempt $record) => $state ? $state.' / '.$record->field_size : null)->placeholder('—'),
                    TextEntry::make('race_time_ms')->label($f('time'))->formatStateUsing(fn (?int $state) => GameAttempt::formatTime($state))->placeholder('—'),
                    TextEntry::make('wall_ms')->label($f('wall'))->formatStateUsing(fn (?int $state) => GameAttempt::formatTime($state))->placeholder('—'),
                    TextEntry::make('top_speed_kmh')->label($f('top_speed'))->placeholder('—'),
                    TextEntry::make('horse_style')->label($f('horse_style'))->formatStateUsing(fn (?string $state) => $state ? __('games.admin.horse_styles.'.$state) : null)->placeholder('—'),
                    TextEntry::make('started_at')->label($f('started_at'))->dateTime('j M Y, H:i:s'),
                    TextEntry::make('finished_at')->label($f('finished_at'))->dateTime('j M Y, H:i:s')->placeholder('—'),
                ]),
            Section::make($f('review'))
                ->icon('heroicon-o-shield-check')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('risk_score')->label($f('risk'))->badge(),
                    TextEntry::make('flags')->label($f('flags'))->badge()->color('warning')->placeholder('—')
                        ->formatStateUsing(fn (?string $state) => $state ? __('games.admin.flags.'.$state) : null),
                    TextEntry::make('review_status')->label($f('review'))->badge()->placeholder('—')
                        ->formatStateUsing(fn (?string $state) => $state ? __('games.admin.review_statuses.'.$state) : null),
                    TextEntry::make('review_note')->label($f('review_note'))->placeholder('—'),
                    TextEntry::make('reviewer.name')->label($f('reviewed_by'))->placeholder('—'),
                    TextEntry::make('ip')->label($f('ip'))->placeholder('—'),
                    TextEntry::make('user_agent')->label($f('user_agent'))->placeholder('—')->columnSpan(2),
                ]),
        ]);
    }

    /** @return array<string, string> */
    private static function options(string $group): array
    {
        return (array) __('games.admin.'.$group);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGameAttempts::route('/'),
            'view' => ViewGameAttempt::route('/{record}'),
        ];
    }
}

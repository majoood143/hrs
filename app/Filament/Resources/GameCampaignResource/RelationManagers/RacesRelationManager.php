<?php

namespace App\Filament\Resources\GameCampaignResource\RelationManagers;

use App\Models\GameAttempt;
use App\Models\GameCampaign;
use App\Models\GameRace;
use App\Services\Games\GameException;
use App\Services\Games\GameScore;
use App\Services\Games\RealRaces;
use App\Services\Racing\RacingUnavailableException;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The campaign's real races (the racing club's calendar, past or upcoming): players choose one and a
 * horse in it. Adding copies the declared field; "Refresh field" reads it again (late changes, and
 * the official result once the race was run). A race can also be typed in by hand (the club's site
 * down, or a race it does not list), and any race's field corrected by hand: a club race keeps those
 * corrections when it is refreshed (Services\Games\RealRaces::saveByHand()).
 */
class RacesRelationManager extends RelationManager
{
    protected static string $relationship = 'races';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('games.admin.races');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $f = fn (string $key) => __('games.admin.fields.'.$key);
        $p = fn (string $key) => __('games.admin.placeholders.'.$key);
        $rtl = ['dir' => 'rtl'];
        $eg = fn (string $value) => __('games.admin.eg', ['value' => $value]);
        $manual = fn (?GameRace $record) => $record === null || $record->isManual();
        $pair = fn (string $key, string $en, string $ar) => [
            TextInput::make("{$key}.en")->label($f($key).' (EN)')->placeholder($en)->maxLength(120),
            TextInput::make("{$key}.ar")->label($f($key).' (AR)')->placeholder($ar)->maxLength(120)->extraInputAttributes($rtl),
        ];

        return $schema->components([
            Section::make(__('games.admin.sections.race_details'))
                ->icon('heroicon-o-flag')
                ->columns(4)
                ->columnSpanFull()
                ->visible($manual)
                ->schema([
                    TextInput::make('en_name')->label($f('en_name'))->placeholder($p('race_en_name'))->required()->maxLength(255)->columnSpan(2),
                    TextInput::make('ar_name')->label($f('ar_name'))->placeholder($p('race_ar_name'))->maxLength(255)->extraInputAttributes($rtl)->columnSpan(2),
                    DatePicker::make('race_date')->label($f('race_date'))->native(false)->required(),
                    TextInput::make('post_time')->label($f('post_time'))->placeholder($eg('16:30'))->regex('/^([01]?\d|2[0-3]):[0-5]\d$/')->maxLength(5),
                    TextInput::make('distance')->label($f('distance'))->placeholder($eg('1600'))->suffix('m')->integer()->minValue(800)->maxValue(4000)->required(),
                    TextInput::make('winning_time')->label($f('winning_time_field'))->placeholder($eg('1:51.15'))->helperText(__('games.admin.help.winning_time'))
                        ->regex('/^\d+:\d{1,2}[:.]\d{1,2}$/')->maxLength(10),
                    TextInput::make('en_surface')->label($f('surface').' (EN)')->placeholder($eg('Dirt'))->maxLength(40),
                    TextInput::make('ar_surface')->label($f('surface').' (AR)')->placeholder('مثل: رملي')->maxLength(40)->extraInputAttributes($rtl),
                ]),
            Section::make(__('games.admin.sections.runners'))
                ->icon('heroicon-o-queue-list')
                ->description(fn (?GameRace $record) => $record && ! $record->isManual() ? __('games.admin.help.hand_edits') : __('games.admin.help.runners'))
                ->columnSpanFull()
                ->schema([
                    Repeater::make('runners')
                        ->hiddenLabel()
                        ->minItems(2)
                        ->maxItems(GameRace::MAX_RUNNERS)
                        ->collapsible()
                        ->collapsed(fn (?GameRace $record) => $record !== null)
                        ->itemLabel(fn (array $state) => trim(($state['no'] ?? '').'. '.($state['name']['en'] ?? '')).(isset($state['rating']) && $state['rating'] !== '' ? ' · OR '.$state['rating'] : ''))
                        ->addActionLabel(__('games.admin.add_runner'))
                        ->schema([
                            Hidden::make('horse_id'),
                            Hidden::make('silks'),
                            Grid::make(6)->schema([
                                TextInput::make('no')->label($f('no'))->placeholder($eg('7'))->required()->maxLength(5)->distinct(),
                                TextInput::make('gate')->label($f('gate'))->placeholder($eg('3'))->integer()->minValue(1)->maxValue(30)->required()->distinct(),
                                TextInput::make('name.en')->label($f('horse').' (EN)')->placeholder($eg('Nawaya (OM)'))->required()->maxLength(120)->columnSpan(2),
                                TextInput::make('name.ar')->label($f('horse').' (AR)')->placeholder('مثل: نوايا (عمان)')->maxLength(120)->extraInputAttributes($rtl)->columnSpan(2),
                                TextInput::make('rating')->label($f('rating'))->placeholder($eg('62'))->integer()->minValue(0)->maxValue(140)
                                    ->live(onBlur: true)
                                    ->hint(fn ($get) => self::multiplierHint((array) $get('../../runners'), (string) $get('no'))),
                                TextInput::make('weight')->label($f('weight'))->placeholder($eg('56.5'))->numeric()->minValue(30)->maxValue(80)->suffix('kg'),
                                TextInput::make('age')->label($f('age'))->placeholder($eg('5'))->integer()->minValue(2)->maxValue(30),
                                ...$pair('colour', $eg('Bay'), 'مثل: أحمر'),
                                ...$pair('sex', $eg('H'), 'مثل: حصان'),
                                TextInput::make('result')->label($f('real_position'))->placeholder($eg('1'))->helperText(__('games.admin.help.result'))->integer()->minValue(1)->maxValue(30),
                                TextInput::make('record.starts')->label($f('starts'))->placeholder($eg('27'))->integer()->minValue(0),
                                TextInput::make('record.first')->label($f('first'))->placeholder($eg('7'))->integer()->minValue(0),
                                TextInput::make('record.second')->label($f('second'))->placeholder($eg('5'))->integer()->minValue(0),
                                TextInput::make('record.third')->label($f('third'))->placeholder($eg('4'))->integer()->minValue(0),
                                ...$pair('jockey', $eg('Amur bin Ali Al Rasbi'), 'مثل: عامر بن علي الراسبي'),
                                ...$pair('trainer', $eg('Hassan bin Mohamed Al Falahi'), 'مثل: حسن بن محمد الفلاحي'),
                                ...$pair('owner', $eg('Khalid bin Mohamed Al Falahi'), 'مثل: خالد بن محمد الفلاحي'),
                                FileUpload::make('silks_upload')->label($f('silks_upload'))->helperText(__('games.admin.help.silks_upload'))
                                    ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(1024)
                                    ->disk('public')->directory('games/silks')->columnSpan(2),
                            ]),
                        ]),
                ]),
        ]);
    }

    /** "Points ×1.25" for a runner, against the field as typed so far. */
    private static function multiplierHint(array $runners, string $no): ?string
    {
        if ($no === '' || count($runners) < 2) {
            return null;
        }

        return __('games.admin.fields.multiplier').' ×'.GameScore::multiplier(GameRace::rankIn(array_values($runners), $no), count($runners));
    }

    public function table(Table $table): Table
    {
        $may = fn () => (bool) Auth::user()?->can('update', $this->getOwnerRecord());

        return $table
            ->description(__('games.admin.help.races'))
            ->columns([
                TextColumn::make('source')->label(__('games.admin.fields.source'))->badge()
                    ->formatStateUsing(fn (string $state, GameRace $record) => __('games.admin.sources.'.$state).($record->locked_nos || $record->removed_nos ? ' ✎' : ''))
                    ->color(fn (string $state) => $state === GameRace::SOURCE_MANUAL ? 'info' : 'gray'),
                TextColumn::make('race_date')->label(__('games.admin.fields.race_date'))->date('j M Y')->description(fn (GameRace $record) => $record->post_time),
                TextColumn::make(app()->getLocale() === 'ar' ? 'ar_name' : 'en_name')->label(__('games.admin.fields.name'))->wrap(),
                TextColumn::make('distance')->label(__('games.admin.fields.distance'))->suffix(' m'),
                TextColumn::make('runners')->label(__('games.admin.fields.runners'))->formatStateUsing(fn (GameRace $record) => (string) $record->fieldSize()),
                IconColumn::make('has_result')->label(__('games.admin.fields.has_result'))->boolean(),
                TextColumn::make('attempts_count')->label(__('games.admin.attempts'))->counts('attempts'),
                TextColumn::make('refreshed_at')->label(__('games.admin.fields.refreshed_at'))->since()->toggleable(),
            ])
            ->defaultSort('race_date')
            ->headerActions([
                CreateAction::make()->label(__('games.admin.add_race_by_hand'))->icon('heroicon-o-pencil-square')->color('gray')
                    ->modalHeading(__('games.admin.add_race_by_hand'))
                    ->modalWidth('7xl')
                    ->visible($may)
                    ->using(fn (array $data) => $this->saveByHand(null, $data)),
                Action::make('add')->label(__('games.admin.add_races'))->icon('heroicon-o-plus')
                    ->visible($may)
                    ->schema([
                        Select::make('day')->label(__('games.admin.fields.race_day'))->placeholder(__('games.admin.placeholders.race_day'))
                            ->options(fn () => $this->safely(fn () => app(RealRaces::class)->raceDays()) ?? [])
                            ->searchable()->required()->live(),
                        CheckboxList::make('races')->label(__('games.admin.races'))
                            ->helperText(__('games.admin.help.races_of_day'))
                            ->options(fn (Get $get) => $get('day') ? ($this->safely(fn () => app(RealRaces::class)->racesOn((string) $get('day'))) ?? []) : [])
                            ->visible(fn (Get $get) => filled($get('day')))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        /** @var GameCampaign $campaign */
                        $campaign = $this->getOwnerRecord();
                        $added = 0;

                        foreach ((array) $data['races'] as $id) {
                            if ($this->safely(fn () => app(RealRaces::class)->import($campaign, (int) $id, (string) $data['day']))) {
                                $added++;
                            }
                        }

                        if ($added > 0) {
                            Notification::make()->success()->title(__('games.admin.races_added', ['count' => $added]))->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('field')->label(__('games.admin.field'))->icon('heroicon-o-queue-list')->color('gray')
                    ->modalHeading(fn (GameRace $record) => $record->name)
                    ->modalContent(fn (GameRace $record) => view('filament.games.field', ['race' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('games.site.close')),
                EditAction::make()->label(__('games.admin.edit_by_hand'))->icon('heroicon-o-pencil-square')
                    ->modalWidth('7xl')
                    ->visible($may)
                    ->mutateRecordDataUsing(fn (array $data, GameRace $record) => $data + ['winning_time' => $record->winning_time_ms ? GameAttempt::formatTime($record->winning_time_ms) : null])
                    ->using(fn (GameRace $record, array $data) => $this->saveByHand($record, $data)),
                Action::make('refresh')->label(__('games.admin.refresh_field'))->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (GameRace $record) => $may() && ! $record->isManual())
                    ->action(function (GameRace $record) {
                        if ($this->safely(fn () => app(RealRaces::class)->import($record->campaign, $record->source_race_id, $record->race_date->format('Y-m-d')))) {
                            Notification::make()->success()->title(__('games.admin.field_refreshed'))->send();
                        }
                    }),
                // a race with races ridden on it is part of the leaderboard's record
                DeleteAction::make()->visible(fn (GameRace $record) => $may() && ! GameAttempt::query()->where('game_race_id', $record->id)->exists()),
            ]);
    }

    /** Save the form through RealRaces; a field the game cannot run is refused with the reason, keeping the form open. */
    private function saveByHand(?GameRace $race, array $data): GameRace
    {
        try {
            return app(RealRaces::class)->saveByHand($this->getOwnerRecord(), $race, $data);
        } catch (GameException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            throw new Halt;
        }
    }

    /**
     * The racing source can be down, and a race may not suit the game: say why, never break the page.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @return T|null
     */
    private function safely(callable $call): mixed
    {
        try {
            return $call();
        } catch (GameException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        } catch (RacingUnavailableException) {
            Notification::make()->danger()->title(__('games.admin.racing_unavailable'))->send();
        }

        return null;
    }
}

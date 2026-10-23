<?php

namespace App\Services\Forms;

use App\Services\Racing\RacingUnavailableException;
use App\Support\Localized;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Carbon;
use Packstub\FormBuilder\Fields\Concerns\HasChoices;
use Packstub\FormBuilder\Fields\Field;
use Packstub\FormBuilder\Fields\FieldType;

/**
 * "Who will win this race?": a dropdown of one race's runners, picked in the editor from the
 * racing calendar (day, then race) instead of typed. The runners are stored with the field like
 * any select's choices (see RacePredictions for where they come from and how they are reloaded),
 * valued by the source's horse id.
 */
class RaceWinnerField extends FieldType
{
    use HasChoices;

    public static function id(): string
    {
        return 'race_winner';
    }

    public function label(): string
    {
        return __('admin_race_predictions.type');
    }

    public function description(): ?string
    {
        return __('admin_race_predictions.type_description');
    }

    public function icon(): string
    {
        return 'heroicon-o-trophy';
    }

    public function group(): string
    {
        return 'choices';
    }

    public function editorSchema(): array
    {
        $predictions = fn (): RacePredictions => app(RacePredictions::class);

        return [
            Select::make('race_date')
                ->label(__('admin_race_predictions.editor.race_date'))
                ->options(function () use ($predictions): array {
                    try {
                        return $predictions()->raceDays();
                    } catch (RacingUnavailableException) {
                        return [];
                    }
                })
                ->helperText(__('admin_race_predictions.editor.race_date_hint'))
                ->searchable()
                ->native(false)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('race_id', null)),
            Select::make('race_id')
                ->label(__('admin_race_predictions.editor.race'))
                ->options(function (Get $get) use ($predictions): array {
                    if (blank($get('race_date'))) {
                        return [];
                    }

                    try {
                        return $predictions()->raceOptions((string) $get('race_date'));
                    } catch (RacingUnavailableException|\InvalidArgumentException) {
                        return [];
                    }
                })
                ->native(false)
                ->required()
                ->live()
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set) use ($predictions): void {
                    if (blank($state)) {
                        return;
                    }

                    try {
                        $race = collect($predictions()->races((string) $get('race_date')))->firstWhere('id', (int) $state);
                    } catch (RacingUnavailableException) {
                        static::unavailable();

                        return;
                    }

                    if ($race === null) {
                        return;
                    }

                    // a new race: its name and its runners (the label only when none is typed yet)
                    foreach ($predictions()->raceLabel($race) as $locale => $label) {
                        if (blank($get("label.{$locale}"))) {
                            $set("label.{$locale}", $label);
                        }
                    }

                    if (blank($get('key'))) {
                        $set('key', "race_{$race['number']}");
                    }

                    static::loadRunners($get, $set, replace: true);
                }),
            // kept with the field, never typed: where the runners came from and when, each runner's card
            // (number, jockey, weight, gate, silks), the race card's header, and the horses withdrawn since
            Hidden::make('runners_from'),
            Hidden::make('runners_updated_at'),
            Hidden::make('runners')->default([]),
            Hidden::make('race_details'),
            Hidden::make('withdrawn')->default([]),
            Toggle::make('sync_runners')
                ->label(__('admin_race_predictions.editor.sync_runners'))
                ->helperText(__('admin_race_predictions.editor.sync_runners_hint'))
                ->default(true)
                ->columnSpanFull(),
            // empty until the source publishes the race's entries; the form stays closed till then (waitingForRunners)
            $this->choicesRepeater()
                ->required(false)
                ->minItems(0)
                ->label(__('admin_race_predictions.editor.runners'))
                ->helperText(fn (Get $get): string => static::runnersStatus($get))
                ->hintAction(
                    Action::make('reloadRunners')
                        ->label(__('admin_race_predictions.editor.reload'))
                        ->icon('heroicon-o-arrow-path')
                        ->disabled(fn (Get $get): bool => blank($get('race_id')))
                        ->action(fn (Get $get, Set $set) => static::loadRunners($get, $set)),
                ),
        ];
    }

    public function rules(Field $field): array
    {
        return $this->choiceRules($field);
    }

    /**
     * A withdrawn horse is no longer offered, but an answer already given for it still reads as its name.
     */
    public function format(mixed $value, Field $field): string
    {
        if (is_scalar($value) && $value !== '' && ! array_key_exists((string) $value, $field->choices())) {
            foreach ((array) $field->option('withdrawn', []) as $row) {
                if (is_array($row) && (string) ($row['value'] ?? '') === (string) $value) {
                    return (string) (Localized::value($row, 'label') ?? $value);
                }
            }
        }

        return parent::format($value, $field);
    }

    /**
     * The race card's header, then one card per runner (silks, number, name, jockey, weight, gate).
     */
    public function view(): string
    {
        return 'forms.fields.race_winner';
    }

    public function formComponent(Field $field): Component
    {
        $descriptions = collect(array_keys($field->choices()))
            ->mapWithKeys(fn (string $value): array => [$value => static::runnerLine(static::runner($field, $value))])
            ->filter()
            ->all();

        return $this->configure(Radio::make($field->key)->options($field->choices())->descriptions($descriptions), $field);
    }

    /**
     * What the race card says about one runner, in the current language (empty for a choice typed by hand).
     *
     * @return array{no: ?string, gate: ?string, weight: ?string, jockey: ?string, image: ?string}
     */
    public static function runner(Field $field, string $value): array
    {
        $runners = (array) $field->option('runners', []);
        $row = (array) ($runners[$value] ?? []);

        return [
            'no' => $row['no'] ?? null,
            'gate' => $row['gate'] ?? null,
            'weight' => $row['weight'] ?? null,
            'jockey' => is_array($row['jockey'] ?? null) ? Localized::value($row, 'jockey') : null,
            'image' => $row['image'] ?? null,
        ];
    }

    /**
     * "Jockey: Amur Al Rasbi · 60.0 kg · Gate 7", leaving out what is not known.
     *
     * @param  array{gate: ?string, weight: ?string, jockey: ?string}  $runner
     */
    public static function runnerLine(array $runner): string
    {
        return implode(' · ', array_filter([
            // a claim stays "(2.0 kg)" inside an Arabic name instead of turning into "(kg 2.0)"
            $runner['jockey'] ? __('race_predictions.jockey', ['name' => preg_replace('/\([\d.]+\s*kg\)/iu', "\u{2066}\$0\u{2069}", $runner['jockey'])]) : null,
            $runner['weight'] ? __('race_predictions.weight', ['weight' => $runner['weight']]) : null,
            $runner['gate'] ? __('race_predictions.gate', ['gate' => $runner['gate']]) : null,
        ]));
    }

    /**
     * The race card's header in the current language (else the first one stored).
     *
     * @return ?array{time: ?string, info: list<string>, facts: list<array{label: string, value: string}>, conditions: ?array{label: string, value: string}, prizes: list<array{place: string, amount: string}>}
     */
    public static function raceDetails(Field $field): ?array
    {
        $details = (array) $field->option('race_details', []);

        return $details[app()->getLocale()] ?? $details['en'] ?? (reset($details) ?: null);
    }

    /**
     * Fill the runners from the source. $replace starts the list afresh (another race was picked);
     * otherwise horses that left the race are kept aside as withdrawn.
     */
    protected static function loadRunners(Get $get, Set $set, bool $replace = false): void
    {
        $predictions = app(RacePredictions::class);
        $data = [
            'race_id' => $get('race_id'),
            'choices' => $replace ? [] : (array) $get('choices'),
            'withdrawn' => $replace ? [] : (array) $get('withdrawn'),
            'runners' => $replace ? [] : (array) $get('runners'),
            'race_details' => $replace ? null : $get('race_details'),
            'runners_from' => $replace ? null : $get('runners_from'),
        ];

        try {
            $reloaded = $predictions->reloaded($data);
        } catch (RacingUnavailableException) {
            static::unavailable();

            return;
        }

        if ($reloaded === $data) {
            Notification::make()->title(__('admin_race_predictions.editor.no_runners'))->warning()->send();

            return;
        }

        foreach (['choices', 'withdrawn', 'runners', 'race_details', 'runners_from', 'runners_updated_at'] as $key) {
            $set($key, $reloaded[$key]);
        }
    }

    protected static function runnersStatus(Get $get): string
    {
        $from = $get('runners_from');

        if (! in_array($from, ['card', 'entries'], true)) {
            return __('admin_race_predictions.editor.runners_hint');
        }

        $at = filled($get('runners_updated_at'))
            ? Carbon::parse((string) $get('runners_updated_at'))->timezone(RacePredictions::timezone())->format('Y-m-d H:i')
            : '—';

        return __("admin_race_predictions.editor.runners_from_{$from}", ['time' => $at]);
    }

    protected static function unavailable(): void
    {
        Notification::make()->title(__('admin_race_predictions.editor.unavailable'))->danger()->send();
    }
}

<?php

namespace App\Filament\Forms;

use App\Services\Forms\RacePredictions;
use App\Services\Racing\RacingUnavailableException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\CreateForm;
use Packstub\FormBuilder\Models\Form;

/**
 * The admin's ways into race prediction forms: start one from a race day (new-form page) and
 * reload the runners of an existing one (forms list and editor).
 */
class RacePredictionActions
{
    /**
     * "Race day predictions": pick a race day, get Name, Mobile and one "who wins?" per race,
     * closing when the first race starts.
     */
    public static function create(): Action
    {
        return Action::make('racePredictions')
            ->label(__('admin_race_predictions.create.action'))
            ->icon('heroicon-o-trophy')
            ->color('gray')
            ->modalHeading(__('admin_race_predictions.create.heading'))
            ->modalDescription(fn (CreateForm $livewire): string => __('admin_race_predictions.create.description')
                .(filled($livewire->data['fields'] ?? null) ? ' '.__('packstub-form-builder::form-builder.templates.replaces') : ''))
            ->modalSubmitActionLabel(__('admin_race_predictions.create.submit'))
            ->schema([
                Select::make('race_date')
                    ->label(__('admin_race_predictions.editor.race_date'))
                    ->options(function (): array {
                        try {
                            return app(RacePredictions::class)->raceDays();
                        } catch (RacingUnavailableException) {
                            return [];
                        }
                    })
                    ->default(function (): ?string {
                        try {
                            return app(RacePredictions::class)->defaultRaceDay();
                        } catch (RacingUnavailableException) {
                            return null;
                        }
                    })
                    ->searchable()
                    ->native(false)
                    ->required(),
            ])
            ->action(function (array $data, CreateForm $livewire, Action $action): void {
                try {
                    $form = app(RacePredictions::class)->form((string) $data['race_date']);
                } catch (RacingUnavailableException) {
                    Notification::make()->title(__('admin_race_predictions.editor.unavailable'))->danger()->send();
                    $action->halt();

                    return;
                }

                if (count($form['fields']) <= 2) {
                    Notification::make()->title(__('admin_race_predictions.create.no_races'))->warning()->send();
                    $action->halt();

                    return;
                }

                $livewire->startWith($form['fields'], $form['name'], array_filter(['closes_at' => $form['closes_at']]));

                $empty = collect($form['fields'])->filter(fn (array $item): bool => ($item['data']['race_id'] ?? null) !== null && $item['data']['choices'] === [])->count();

                if ($empty > 0) {
                    Notification::make()
                        ->title(trans_choice('admin_race_predictions.create.runners_pending', $empty, ['count' => $empty]))
                        ->body(__('admin_race_predictions.create.runners_pending_body'))
                        ->warning()
                        ->send();
                }
            });
    }

    /**
     * "Reload runners": every race prediction field left in step with the race card, from the source now.
     */
    public static function reload(): Action
    {
        return Action::make('reloadRaceRunners')
            ->label(__('admin_race_predictions.reload.action'))
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (Form $record): bool => RacePredictions::hasRaceFields($record) && (bool) auth()->user()?->can('update', $record))
            ->action(function (Form $record, mixed $livewire): void {
                try {
                    $changed = app(RacePredictions::class)->reload($record);
                } catch (RacingUnavailableException) {
                    Notification::make()->title(__('admin_race_predictions.editor.unavailable'))->danger()->send();

                    return;
                }

                // the editor shows the new runners at once, keeping its other unsaved changes
                // (refreshFormData() cannot: it only refills paths that hold a plain value)
                if ($livewire instanceof EditRecord && $changed > 0) {
                    $livewire->form->fill([...$livewire->form->getRawState(), 'fields' => $record->fields]);
                }

                Notification::make()
                    ->title($changed > 0
                        ? trans_choice('admin_race_predictions.reload.done', $changed, ['count' => $changed])
                        : __('admin_race_predictions.reload.unchanged'))
                    ->success()
                    ->send();
            });
    }
}

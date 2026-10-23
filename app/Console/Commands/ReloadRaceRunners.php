<?php

namespace App\Console\Commands;

use App\Services\Forms\RacePredictions;
use App\Services\Forms\RaceWinnerField;
use App\Services\Racing\RacingUnavailableException;
use Illuminate\Console\Command;
use Packstub\FormBuilder\FormBuilder;

class ReloadRaceRunners extends Command
{
    protected $signature = 'forms:reload-race-runners';

    protected $description = 'Bring the runners of open race prediction forms up to date with the race card';

    public function handle(RacePredictions $predictions): int
    {
        $forms = FormBuilder::formModel()::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->where('fields', 'like', '%"'.RaceWinnerField::id().'"%')
            ->get()
            ->filter(fn ($form): bool => RacePredictions::hasRaceFields($form));

        $failed = false;

        foreach ($forms as $form) {
            try {
                $changed = $predictions->reload($form);

                $this->info("{$form->slug}: {$changed} race(s) updated.");
            } catch (RacingUnavailableException $e) {
                $this->error("{$form->slug}: {$e->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

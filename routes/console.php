<?php

<<<<<<< HEAD
use Illuminate\Foundation\Console\ClosureCommand;
=======
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
<<<<<<< HEAD
    /** @var ClosureCommand $this */
=======
>>>>>>> 9019a60 (Baseline before Filament v4 upgrade)
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('transfer-posts:expire')->dailyAt('00:05');
Schedule::command('horse-sale-posts:expire')->dailyAt('00:10');

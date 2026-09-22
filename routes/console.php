<?php

use App\Models\CustomerOtp;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('transfer-posts:expire')->dailyAt('00:05');
Schedule::command('horse-sale-posts:expire')->dailyAt('00:10');

// Unpaid service orders are cancelled after config('payments.pending_ttl_minutes'); a Thawani
// session can still be paid for 24 hours afterwards, so recently cancelled ones are re-checked.
Schedule::command('orders:expire-pending')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('orders:recover-late-payments')->everyFiveMinutes()->withoutOverlapping();

// One-time codes are only needed for minutes; keep a day of them for the rate limits, then drop them.
Schedule::command('model:prune', ['--model' => [CustomerOtp::class]])->daily();

// The handicap ratings list is cached for racing.handicap_ttl (1 hour by default) and can take
// up to racing.handicap_timeout (45s) to fetch cold; warming it well inside that window means
// a visitor essentially never hits a cold cache (RacingClient::handicap() still holds a lock
// around a cold fetch as a safety net, e.g. right after a deploy).
Schedule::command('racing:warm')->everyThirtyMinutes()->withoutOverlapping();

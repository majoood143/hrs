<?php

namespace App\Listeners;

use App\Events\StableRegistered;
use App\Jobs\SendStableEmail;
use App\Mail\StableRegisteredMail;
use App\Support\StableApprovers;

/** Emails the admins who approve stables. Auto-discovered (app/Listeners): never Event::listen it. */
class NotifyAdminsOfStableRegistration
{
    public function handle(StableRegistered $event): void
    {
        foreach (StableApprovers::emails() as $address) {
            SendStableEmail::dispatch($address, new StableRegisteredMail($event->stable, $event->owner), 'stable_registered')->afterCommit();
        }
    }
}

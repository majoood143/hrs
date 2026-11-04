<?php

namespace App\Console\Commands;

use App\Jobs\SendPushCampaign;
use App\Models\PushCampaign;
use Illuminate\Console\Command;

/** Sends the push campaigns whose time has come (admin → Mobile app → Notifications → "Send at"). */
class SendDuePushCampaigns extends Command
{
    protected $signature = 'push:send-due';

    protected $description = 'Send the scheduled push campaigns that are due';

    public function handle(): int
    {
        PushCampaign::query()->due()->pluck('id')->each(fn (int $id) => SendPushCampaign::dispatch($id));

        return self::SUCCESS;
    }
}

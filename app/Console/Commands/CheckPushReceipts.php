<?php

namespace App\Console\Commands;

use App\Services\Push\ExpoPush;
use Illuminate\Console\Command;

/** Reads Expo's push receipts and marks the phones the app was removed from (ExpoPush::checkReceipts()). */
class CheckPushReceipts extends Command
{
    protected $signature = 'push:check-receipts';

    protected $description = 'Read Expo push receipts and mark phones the app was removed from';

    public function handle(ExpoPush $push): int
    {
        $this->info("Marked {$push->checkReceipts()} phones as uninstalled.");

        return self::SUCCESS;
    }
}

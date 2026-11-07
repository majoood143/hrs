<?php

namespace App\Console\Commands;

use App\Models\BookingSlot;
use App\Models\Stable;
use App\Services\Stables\StableSalesReports;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/** Sends the sales reports due now (admin → the stable → Sales report), at most one a day per stable. */
class SendStableSalesReports extends Command
{
    protected $signature = 'stables:send-sales-reports';

    protected $description = 'Send stable owners the daily or weekly sales reports that are due';

    public function handle(StableSalesReports $reports): int
    {
        $now = CarbonImmutable::now(BookingSlot::timezone());
        $sent = 0;

        Stable::query()->active()->whereNotNull('sales_report')->each(function (Stable $stable) use ($reports, $now, &$sent): void {
            if (! $reports->isDue($stable, $now)) {
                return;
            }

            // marked first: a failure part way must not message the owners twice
            $stable->forceFill(['sales_report_sent_on' => $now->toDateString()])->saveQuietly();

            try {
                $reports->send($stable, $now);
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        });

        $this->info("Sent {$sent} sales report(s).");

        return self::SUCCESS;
    }
}

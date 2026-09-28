<?php

namespace App\Console\Commands;

use App\Models\Stable;
use App\Services\Stables\SlotGenerator;
use Illuminate\Console\Command;
use Throwable;

/**
 * Rolls every stable's booking window forward one day at a time: the weekly schedules get slots for
 * the newly reached days. Existing slots are left as the owners changed them.
 */
class GenerateStableSlots extends Command
{
    protected $signature = 'stables:generate-slots';

    protected $description = 'Create booking slots from the stables\' weekly schedules, up to each stable\'s booking window';

    public function handle(SlotGenerator $generator): int
    {
        $created = 0;

        Stable::query()->whereHas('schedules', fn ($q) => $q->where('is_active', true))->each(function (Stable $stable) use ($generator, &$created): void {
            try {
                $created += $generator->syncStable($stable)->created;
            } catch (Throwable $e) {
                // one stable's bad data must not stop the others
                report($e);
                $this->error("Stable #{$stable->getKey()}: {$e->getMessage()}");
            }
        });

        $this->info("Created {$created} slots.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Models\Concerns;

use App\Models\Stable;
use App\Models\User;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The stable's change log: every change to a stable's own data (by its owner, or by an admin
 * working in the stable's panel) is recorded with who made it. Each entry carries the stable's id
 * and whether an admin made it on the stable's behalf, so both the stable's admin page and the
 * owner's own log can list them.
 *
 * A model lists what it logs in stableActivityAttributes() (never secrets), and may limit the
 * events with a static $recordEvents.
 */
trait LogsStableActivity
{
    use LogsActivity;

    /** @return list<string> */
    abstract protected function stableActivityAttributes(): array;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('stable')
            ->logOnly($this->stableActivityAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $stableId = $this instanceof Stable ? $this->getKey() : $this->getAttribute('stable_id');
        $user = auth()->user();

        $activity->properties = $activity->properties->merge([
            'stable_id' => $stableId,
            // an admin (not one of the stable's own people) changed it on the stable's behalf
            'as_admin' => $user instanceof User && ! $user->belongsToStable($stableId) && $user->managesStables(),
        ]);
    }
}

<?php

namespace App\Filament\Stable\Widgets;

use App\Filament\Stable\Resources\StableOfferings\StableOfferingResource;
use App\Filament\Stable\Resources\StableSchedules\StableScheduleResource;
use App\Models\Stable;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Tells the owner where the stable stands (waiting for approval, rejected with the reason,
 * suspended, or live) and what to set up next.
 */
class ApprovalStatusWidget extends Widget
{
    protected static ?int $sort = -10;

    // the first thing an owner must see: rendered with the page, not after it
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.stable.widgets.approval-status';

    protected function getViewData(): array
    {
        /** @var Stable $stable */
        $stable = Filament::getTenant();

        return [
            'stable' => $stable,
            'status' => $stable->approval_status,
            'hasOfferings' => $stable->offerings()->exists(),
            'hasSchedules' => $stable->schedules()->exists(),
            'offeringsUrl' => StableOfferingResource::getUrl('index'),
            'schedulesUrl' => StableScheduleResource::getUrl('index'),
            'publicUrl' => $stable->isApproved() && $stable->is_active ? route('stables.show', $stable->slug) : null,
        ];
    }
}

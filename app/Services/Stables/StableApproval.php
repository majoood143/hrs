<?php

namespace App\Services\Stables;

use App\Enums\FeeType;
use App\Enums\StableApprovalStatus;
use App\Events\StableReviewed;
use App\Models\Stable;
use App\Models\User;
use InvalidArgumentException;

/**
 * The admins' decisions on a stable. Approving needs a commission (there is no global one: every
 * stable has its own), and opens the stable's slots right away.
 */
class StableApproval
{
    public function __construct(private readonly SlotGenerator $slots) {}

    public function approve(Stable $stable, FeeType $commissionType, float|string $commissionValue, ?User $by = null): Stable
    {
        $this->setCommission($stable, $commissionType, $commissionValue);

        $stable->forceFill([
            'approval_status' => StableApprovalStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $by?->getKey(),
            'rejection_reason' => null,
        ])->save();

        $this->slots->syncStable($stable);

        StableReviewed::dispatch($stable);

        return $stable;
    }

    public function reject(Stable $stable, string $reason, ?User $by = null): Stable
    {
        return $this->decline($stable, StableApprovalStatus::Rejected, $reason, $by);
    }

    /** Takes an approved stable off the site (bookings already made stay). */
    public function suspend(Stable $stable, string $reason, ?User $by = null): Stable
    {
        return $this->decline($stable, StableApprovalStatus::Suspended, $reason, $by);
    }

    public function setCommission(Stable $stable, FeeType $type, float|string $value): Stable
    {
        $value = round((float) $value, 3);

        if ($value < 0 || ($type === FeeType::Percentage && $value > 100)) {
            throw new InvalidArgumentException('The commission must be 0–100% or a positive amount.');
        }

        $stable->forceFill(['commission_type' => $type, 'commission_value' => $value])->save();

        return $stable;
    }

    private function decline(Stable $stable, StableApprovalStatus $status, string $reason, ?User $by): Stable
    {
        $stable->forceFill([
            'approval_status' => $status,
            'approved_by' => $by?->getKey(),
            'rejection_reason' => trim($reason),
        ])->save();

        StableReviewed::dispatch($stable);

        return $stable;
    }
}

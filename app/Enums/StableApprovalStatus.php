<?php

namespace App\Enums;

/** Where a stable stands with the admins. Only an approved stable is shown on the site and takes bookings. */
enum StableApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return __('stable_panel.approval.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::Pending => 'warning',
            self::Rejected, self::Suspended => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Approved => 'heroicon-o-check-badge',
            self::Pending => 'heroicon-o-clock',
            self::Rejected => 'heroicon-o-x-circle',
            self::Suspended => 'heroicon-o-pause-circle',
        };
    }

    /** @return array<string, string> value => label, for selects and filters */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}

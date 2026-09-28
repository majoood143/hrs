<?php

namespace App\Enums;

enum StableBookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('stable_panel.booking_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed, self::Completed => 'success',
            self::Pending => 'warning',
            self::NoShow => 'gray',
            self::Cancelled => 'danger',
        };
    }

    /**
     * Whether a booking in this state takes places in its slot. A pending one does too: it is a
     * checkout in progress, and the order sweep cancels it if it is never paid.
     *
     * @return list<string>
     */
    public static function holdingValues(): array
    {
        return [self::Pending->value, self::Confirmed->value, self::Completed->value, self::NoShow->value];
    }
}

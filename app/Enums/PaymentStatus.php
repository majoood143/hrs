<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Free = 'free';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    // a stable booking the customer pays for at the stable: nothing is collected online
    case OnSite = 'on_site';

    public function label(): string
    {
        return __('orders.payment_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::Free => 'gray',
            self::Pending => 'warning',
            self::Failed, self::Cancelled => 'danger',
            self::Refunded => 'info',
            self::OnSite => 'info',
        };
    }
}

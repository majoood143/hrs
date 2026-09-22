<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case New = 'new';
    case InReview = 'in_review';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('orders.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::New, self::InReview, self::Processing => 'info',
            self::PendingPayment => 'warning',
            self::Rejected, self::Cancelled => 'danger',
        };
    }
}

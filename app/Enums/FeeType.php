<?php

namespace App\Enums;

enum FeeType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return __('admin_service_fee_setting.types.'.$this->value);
    }
}

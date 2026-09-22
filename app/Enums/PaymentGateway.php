<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Thawani = 'thawani';
    case Nbo = 'nbo';
    case CcAvenue = 'ccavenue';
    case Demo = 'demo';

    public function label(): string
    {
        return match ($this) {
            self::Thawani => 'Thawani',
            self::Nbo => 'NBO',
            self::CcAvenue => 'CCAvenue (Bank Muscat)',
            self::Demo => __('payment_gateways.options.demo'),
        };
    }
}

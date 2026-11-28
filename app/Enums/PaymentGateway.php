<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Thawani = 'thawani';
    case Nbo = 'nbo';
    case CcAvenue = 'ccavenue';
    case Ompay = 'ompay';
    case Demo = 'demo';
    /** Recorded by an admin (an auction invoice above the card limit): no checkout, no gateway class. */
    case BankTransfer = 'bank_transfer';
    /** Taken at a stable's desk (the POS: cash, card machine, transfer, wallet): no checkout, no gateway class. */
    case Desk = 'desk';

    public function label(): string
    {
        return match ($this) {
            self::Thawani => 'Thawani',
            self::Nbo => 'NBO',
            self::CcAvenue => 'CCAvenue (Bank Muscat)',
            self::Ompay => 'OMPAY',
            self::Demo => __('payment_gateways.options.demo'),
            self::BankTransfer => __('payment_gateways.options.bank_transfer'),
            self::Desk => __('payment_gateways.options.desk'),
        };
    }
}

<?php

namespace App\Services\Auctions;

/** Auction amounts are `decimal(10,3)` OMR in the database and integer baisa in every calculation. */
final class Money
{
    public static function baisa(mixed $omr): int
    {
        return (int) round((float) $omr * 1000);
    }

    public static function omr(int $baisa): string
    {
        return number_format($baisa / 1000, 3, '.', '');
    }
}

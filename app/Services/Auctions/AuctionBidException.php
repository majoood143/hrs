<?php

namespace App\Services\Auctions;

use RuntimeException;

/** A refused bid; the message is already in the current language (`auctions.errors.{reason}`). */
class AuctionBidException extends RuntimeException
{
    /** @param  array<string, string>  $params */
    public function __construct(public readonly string $reason, array $params = [])
    {
        parent::__construct(__('auctions.errors.'.$reason, $params));
    }
}

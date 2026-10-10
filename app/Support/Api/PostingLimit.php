<?php

namespace App\Support\Api;

use App\Models\Farrier;
use App\Models\HorseSalePost;
use App\Models\ToolSalePost;
use App\Models\TransferPost;

/**
 * How many posts one verified phone may publish from the app in 24 hours (all kinds together).
 * Counted from the posts themselves: the app stores the verified phone as their contact number.
 */
final class PostingLimit
{
    public const PER_DAY = 5;

    /** The contact number an app post carries: the verified phone, with its +. */
    public static function contactNumber(string $phone): string
    {
        return '+'.$phone;
    }

    public static function reached(string $phone): bool
    {
        $since = now()->subDay();
        $contact = self::contactNumber($phone);

        $count = collect([HorseSalePost::class, ToolSalePost::class, Farrier::class, TransferPost::class])
            ->sum(fn (string $model) => $model::query()->where('contact_number', $contact)->where('created_at', '>=', $since)->count());

        return $count >= self::PER_DAY;
    }
}

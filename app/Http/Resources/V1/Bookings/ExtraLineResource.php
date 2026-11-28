<?php

namespace App\Http\Resources\V1\Bookings;

use App\Http\Resources\V1\MoneyResource;
use App\Models\StableBookingExtra;
use App\Models\StableExtra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An extra on a booking (as it was booked), or chosen on the booking form (an array: extra, quantity,
 * amount in baisa, from `StableExtra::lines()`).
 *
 * @property-read StableBookingExtra|array{extra: StableExtra, quantity: int, amount: int} $resource
 */
class ExtraLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $line = $this->resource;
        $booked = $line instanceof StableBookingExtra;

        return [
            /** @var int|null the extra (null once deleted) */
            'extra_id' => $booked ? $line->stable_extra_id : (int) $line['extra']->id,
            'name' => $booked ? $line->name : (string) $line['extra']->name,
            'quantity' => $booked ? (int) $line->quantity : (int) $line['quantity'],
            /** For this booking, before the fee and VAT. */
            'amount' => new MoneyResource($booked ? $line->amount : $line['amount'] / 1000),
        ];
    }
}

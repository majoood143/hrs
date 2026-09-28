<?php

namespace App\Services\Stables;

use App\Enums\StableBookingStatus;
use App\Models\Customer;
use App\Models\StableBooking;
use App\Models\StableReview;
use Illuminate\Database\QueryException;

/**
 * Reviews of sessions: only the customer who booked may rate it, once, after the stable marked it
 * attended and within REVIEW_DAYS of the session. Published straight away; admins can hide one and
 * the stable can reply.
 */
class StableReviews
{
    public const REVIEW_DAYS = 60;

    public function canReview(StableBooking $booking, ?Customer $customer): bool
    {
        $booking->loadMissing(['order', 'slot', 'review']);

        return $customer !== null
            && $booking->status === StableBookingStatus::Completed
            && $booking->review === null
            && $booking->order !== null
            && ((int) $booking->order->customer_id === (int) $customer->getKey() || $booking->order->customer_phone === $customer->phone)
            && $booking->slot !== null
            && $booking->slot->startsAt()->addDays(self::REVIEW_DAYS)->isFuture();
    }

    /** @throws BookingUnavailable */
    public function submit(StableBooking $booking, Customer $customer, int $rating, ?string $comment): StableReview
    {
        if (! $this->canReview($booking, $customer)) {
            throw new BookingUnavailable(__('stable_reviews.errors.cannot_review'));
        }

        try {
            return StableReview::create([
                'stable_id' => $booking->stable_id,
                'stable_booking_id' => $booking->getKey(),
                'stable_offering_id' => $booking->stable_offering_id,
                'customer_id' => $customer->getKey(),
                'author_name' => $customer->name ?: $booking->order?->customer_name,
                'rating' => max(1, min(5, $rating)),
                'comment' => filled($comment) ? trim($comment) : null,
            ]);
        } catch (QueryException) {
            // the unique booking index: a second submit of the same form
            throw new BookingUnavailable(__('stable_reviews.errors.cannot_review'));
        }
    }

    public function reply(StableReview $review, ?string $reply): StableReview
    {
        $review->forceFill([
            'reply' => filled($reply) ? trim($reply) : null,
            'replied_at' => filled($reply) ? now() : null,
        ])->save();

        return $review;
    }

    public function setVisible(StableReview $review, bool $visible): StableReview
    {
        $review->forceFill(['is_visible' => $visible])->save();

        return $review;
    }
}

<?php

namespace App\Listeners;

use App\Events\StableReviewed;
use App\Jobs\SendStableEmail;
use App\Mail\StableReviewedMail;
use App\Models\User;
use App\Services\Sms\SmsManager;
use App\Support\Locale;
use Throwable;

/**
 * Tells a stable's owners what the admins decided, by email and SMS, in each owner's language.
 * Auto-discovered (app/Listeners): never Event::listen it.
 */
class NotifyOwnersOfStableReview
{
    public function __construct(private readonly SmsManager $sms) {}

    public function handle(StableReviewed $event): void
    {
        $stable = $event->stable;
        $status = $stable->approval_status->value;

        foreach ($stable->owners()->get() as $owner) {
            /** @var User $owner */
            $locale = $owner->locale ?: config('languages.default', 'en');

            if (filter_var($owner->email, FILTER_VALIDATE_EMAIL)) {
                SendStableEmail::dispatch($owner->email, new StableReviewedMail($stable, $locale), 'stable_'.$status)->afterCommit();
            }

            if ($owner->phone && $this->sms->canSend()) {
                try {
                    $text = Locale::within($locale, fn () => __('stable_panel.sms.'.$status, ['stable' => $stable->name, 'link' => url('/stable')]));
                    $this->sms->send($owner->phone, $text, 'stable_'.$status);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }
}

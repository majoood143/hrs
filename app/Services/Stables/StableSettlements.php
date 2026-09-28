<?php

namespace App\Services\Stables;

use App\Jobs\SendStableEmail;
use App\Mail\StableSettlementMail;
use App\Models\Stable;
use App\Models\StableSettlement;
use App\Models\User;
use App\Services\Sms\SmsManager;
use App\Support\Locale;
use App\Support\Money;
use InvalidArgumentException;
use Throwable;

/** Records money that moved between us and a stable, and tells the stable's owners. */
class StableSettlements
{
    public function __construct(private readonly SmsManager $sms) {}

    public function record(
        Stable $stable,
        string $direction,
        float|string $amount,
        string $paidOn,
        string $method = 'bank_transfer',
        ?string $reference = null,
        ?string $note = null,
        ?User $by = null,
    ): StableSettlement {
        $amount = round((float) $amount, 3);

        if (! in_array($direction, [StableSettlement::PAYOUT, StableSettlement::RECEIPT], true) || $amount <= 0) {
            throw new InvalidArgumentException('A settlement is a payout or a receipt of a positive amount.');
        }

        $settlement = $stable->settlements()->create([
            'direction' => $direction,
            'amount' => $amount,
            'paid_on' => $paidOn,
            'method' => in_array($method, StableSettlement::METHODS, true) ? $method : 'other',
            'reference' => filled($reference) ? trim($reference) : null,
            'note' => filled($note) ? trim($note) : null,
            'recorded_by' => $by?->getKey(),
        ]);

        $this->tellOwners($stable, $settlement);

        return $settlement;
    }

    private function tellOwners(Stable $stable, StableSettlement $settlement): void
    {
        foreach ($stable->owners()->get() as $owner) {
            $locale = $owner->locale ?: config('languages.default', 'en');

            if (filter_var($owner->email, FILTER_VALIDATE_EMAIL)) {
                SendStableEmail::dispatch($owner->email, new StableSettlementMail($settlement, $locale), 'stable_'.$settlement->direction)->afterCommit();
            }

            if ($owner->phone && $this->sms->canSend()) {
                try {
                    $this->sms->send($owner->phone, Locale::within($locale, fn () => __('stable_statement.sms.'.$settlement->direction, [
                        'amount' => Money::format($settlement->amountBaisa()),
                        'stable' => $stable->name,
                        'date' => $settlement->paid_on->toDateString(),
                    ])), 'stable_'.$settlement->direction);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }
}

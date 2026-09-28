<?php

namespace App\Services\Stables;

use App\Enums\PaymentGateway;
use App\Jobs\SendStableEmail;
use App\Mail\StablePaymentAccountMail;
use App\Models\StablePaymentAccount;
use App\Models\User;
use App\Services\Payments\PaymentGateways;
use App\Services\Sms\SmsManager;
use App\Support\Locale;
use App\Support\StableApprovers;
use Throwable;

/**
 * A stable's own gateway keys: checking them against the gateway, and the admins' approval (only
 * approved keys are ever used to take a payment). Both sides are told of each step.
 */
class StablePaymentAccounts
{
    public function __construct(
        private readonly PaymentGateways $gateways,
        private readonly SmsManager $sms,
    ) {}

    /** @return array{0: bool, 1: string} */
    public function test(StablePaymentAccount $account): array
    {
        try {
            $gateway = $this->gateways->get($account->gateway)->forAccount($account);
            [$ok, $message] = method_exists($gateway, 'testConnection') ? $gateway->testConnection() : [false, __('payments.test.incomplete')];
        } catch (Throwable $e) {
            report($e);
            [$ok, $message] = [false, __('payments.test.unreachable', ['error' => $e->getMessage()])];
        }

        // not a change of keys: must not send the account back for approval
        StablePaymentAccount::query()->whereKey($account->getKey())->update([
            'last_tested_at' => now(),
            'last_test_ok' => $ok,
            'last_test_message' => mb_substr($message, 0, 250),
        ]);
        $account->refresh();

        return [$ok, $message];
    }

    /**
     * Saves keys the owner entered: an empty secret keeps the stored one. New keys go back to
     * "waiting for approval", are tested, and the admins are told.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function saveKeys(int $stableId, PaymentGateway $gateway, array $credentials, bool $testMode): StablePaymentAccount
    {
        $account = StablePaymentAccount::query()->firstOrNew(['stable_id' => $stableId, 'gateway' => $gateway->value]);
        $stored = $account->exists ? ($account->credentials ?? []) : [];

        foreach (array_keys(StablePaymentAccount::FIELDS[$gateway->value] ?? []) as $key) {
            $value = trim((string) ($credentials[$key] ?? ''));
            $secret = StablePaymentAccount::FIELDS[$gateway->value][$key];

            if ($value !== '' || ! $secret) {
                $stored[$key] = $value;
            }
        }

        $account->fill(['credentials' => $stored, 'test_mode' => $testMode]);

        if (! $account->exists || $account->isDirty()) {
            $account->save();
            $this->test($account);
            $this->tellAdmins($account);
        }

        return $account;
    }

    public function approve(StablePaymentAccount $account, ?User $by = null): void
    {
        $account->forceFill([
            'status' => StablePaymentAccount::APPROVED,
            'reviewed_by' => $by?->getKey(),
            'reviewed_at' => now(),
            'review_note' => null,
        ])->save();

        $this->tellOwners($account);
    }

    public function reject(StablePaymentAccount $account, string $note, ?User $by = null): void
    {
        $account->forceFill([
            'status' => StablePaymentAccount::REJECTED,
            'reviewed_by' => $by?->getKey(),
            'reviewed_at' => now(),
            'review_note' => trim($note),
        ])->save();

        $this->tellOwners($account);
    }

    private function tellAdmins(StablePaymentAccount $account): void
    {
        foreach (StableApprovers::emails() as $address) {
            SendStableEmail::dispatch($address, new StablePaymentAccountMail($account, 'admin', config('languages.default', 'en')), 'stable_keys_submitted')->afterCommit();
        }
    }

    private function tellOwners(StablePaymentAccount $account): void
    {
        $stable = $account->stable;

        foreach ($stable?->owners()->get() ?? [] as $owner) {
            $locale = $owner->locale ?: config('languages.default', 'en');

            if (filter_var($owner->email, FILTER_VALIDATE_EMAIL)) {
                SendStableEmail::dispatch($owner->email, new StablePaymentAccountMail($account, 'owner', $locale), 'stable_keys_'.$account->status)->afterCommit();
            }

            if ($owner->phone && $this->sms->canSend()) {
                try {
                    $this->sms->send($owner->phone, Locale::within($locale, fn () => __('stable_panel.payments.sms_'.$account->status, [
                        'stable' => $stable->name,
                        'gateway' => $account->gateway->label(),
                    ])), 'stable_keys_'.$account->status);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }
    }
}

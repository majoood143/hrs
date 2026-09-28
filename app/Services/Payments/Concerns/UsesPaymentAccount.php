<?php

namespace App\Services\Payments\Concerns;

use App\Models\SiteSetting;
use App\Models\StablePaymentAccount;
use App\Support\SecretSetting;

/**
 * Lets a gateway work with a stable's own account instead of the site's: forAccount() returns a
 * copy that reads its keys from the account. Without an account it reads the Payment Gateways
 * settings exactly as before ("<gateway>.<key>").
 */
trait UsesPaymentAccount
{
    protected ?StablePaymentAccount $account = null;

    public function forAccount(?StablePaymentAccount $account): static
    {
        $copy = clone $this;
        $copy->account = $account;

        return $copy;
    }

    public function account(): ?StablePaymentAccount
    {
        return $this->account;
    }

    protected function setting(string $key, mixed $default = ''): mixed
    {
        if ($this->account) {
            return $key === 'test_mode' ? $this->account->test_mode : ($this->account->get($key) ?: $default);
        }

        return SiteSetting::get($this->gateway()->value.'.'.$key, $default);
    }

    protected function secret(string $key): string
    {
        return $this->account ? $this->account->get($key) : SecretSetting::get($this->gateway()->value.'.'.$key);
    }

    /** Query parameters a callback URL carries so the answer is read with the right account's keys. */
    protected function accountQuery(): array
    {
        return $this->account ? ['account' => $this->account->getKey()] : [];
    }
}

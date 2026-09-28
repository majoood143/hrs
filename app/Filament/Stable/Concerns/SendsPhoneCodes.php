<?php

namespace App\Filament\Stable\Concerns;

use App\Services\Auth\CustomerOtpService;
use App\Services\Auth\OtpRequestResult;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;

/**
 * "Send me a code" on the owner sign-up and sign-in pages: the same SMS codes, lifetime and limits
 * as the customer phone sign-in (CustomerOtpService).
 */
trait SendsPhoneCodes
{
    protected function sendCodeAction(string $phoneField = 'phone'): Action
    {
        return Action::make('sendCode')
            ->label(__('account.send_code'))
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->action(function (Get $get) use ($phoneField): void {
                $this->sendPhoneCode($get($phoneField));
            });
    }

    protected function sendPhoneCode(?string $phone): void
    {
        $result = app(CustomerOtpService::class)->request($phone, request()->ip());

        $notification = match ($result->status) {
            OtpRequestResult::SENT => Notification::make()->success()->title(__('account.code_sent'))
                ->body($result->demoCode ? __('account.demo_code').' '.$result->demoCode : null),
            OtpRequestResult::THROTTLED => Notification::make()->warning()->title(__('account.wait_before_resend', ['seconds' => $result->retryAfter])),
            OtpRequestResult::INVALID_PHONE => Notification::make()->danger()->title(__('account.phone_invalid')),
            default => Notification::make()->danger()->title(__('account.sms_unavailable')),
        };

        $notification->send();
    }

    /** The code check: true, or a translated reason it failed. */
    protected function phoneCodeError(?string $phone, ?string $code): ?string
    {
        return match (app(CustomerOtpService::class)->check($phone, $code)) {
            CustomerOtpService::VERIFIED => null,
            CustomerOtpService::EXPIRED => __('account.code_expired'),
            default => __('account.code_wrong'),
        };
    }
}

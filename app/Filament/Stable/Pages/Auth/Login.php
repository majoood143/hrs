<?php

namespace App\Filament\Stable\Pages\Auth;

use App\Filament\Stable\Concerns\SendsPhoneCodes;
use App\Models\User;
use App\Support\PhoneNumber;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use MarcoGermani87\FilamentCaptcha\Forms\Components\CaptchaField;

/** Stable owners sign in with email + password, or with their phone + an SMS code. */
class Login extends BaseLogin
{
    use SendsPhoneCodes;

    public function getHeading(): string|Htmlable|null
    {
        return __('stable_panel.login.heading');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ToggleButtons::make('method')
                    ->hiddenLabel()
                    ->options([
                        'email' => __('stable_panel.login.with_email'),
                        'phone' => __('stable_panel.login.with_phone'),
                    ])
                    ->icons([
                        'email' => 'heroicon-o-envelope',
                        'phone' => 'heroicon-o-device-phone-mobile',
                    ])
                    ->default('email')
                    ->inline()
                    ->grouped()
                    ->live(),
                $this->getEmailFormComponent()->visible(fn (Get $get) => $get('method') !== 'phone'),
                $this->getPasswordFormComponent()->visible(fn (Get $get) => $get('method') !== 'phone'),
                TextInput::make('phone')
                    ->label(__('stable_panel.fields.mobile'))
                    ->helperText(__('account.phone_hint'))
                    ->tel()
                    ->required()
                    ->maxLength(20)
                    ->suffixAction($this->sendCodeAction())
                    ->visible(fn (Get $get) => $get('method') === 'phone'),
                TextInput::make('otp_code')
                    ->label(__('account.code_label'))
                    ->required()
                    ->rule('digits:6')
                    ->extraInputAttributes(['inputmode' => 'numeric', 'autocomplete' => 'one-time-code'])
                    ->visible(fn (Get $get) => $get('method') === 'phone'),
                CaptchaField::make('captcha'),
                $this->getRememberFormComponent(),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        if (($this->data['method'] ?? 'email') !== 'phone') {
            return parent::authenticate();
        }

        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if ($error = $this->phoneCodeError($data['phone'] ?? null, $data['otp_code'] ?? null)) {
            throw ValidationException::withMessages(['data.otp_code' => $error]);
        }

        $user = User::query()
            ->where('phone', PhoneNumber::normalize($data['phone']))
            ->whereNotNull('phone_verified_at')
            ->where('type', User::TYPE_STABLE_OWNER)
            ->first();

        if (! $user || ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            throw ValidationException::withMessages(['data.phone' => __('stable_panel.login.no_account')]);
        }

        Filament::auth()->login($user, (bool) ($data['remember'] ?? false));

        session()->regenerate();

        return app(LoginResponse::class);
    }
}

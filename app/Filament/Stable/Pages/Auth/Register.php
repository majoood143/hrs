<?php

namespace App\Filament\Stable\Pages\Auth;

use App\Filament\Stable\Concerns\SendsPhoneCodes;
use App\Filament\Stable\Schemas\StableProfileForm;
use App\Models\User;
use App\Services\Stables\StableRegistration;
use App\Support\PhoneNumber;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use MarcoGermani87\FilamentCaptcha\Forms\Components\CaptchaField;
use SensitiveParameter;

/**
 * A stable owner signs up with their first stable: their details (the phone confirmed by an SMS
 * code), then the stable's. The stable waits for the admins; the owner can set it up meanwhile.
 */
class Register extends BaseRegister
{
    use SendsPhoneCodes;

    protected Width|string|null $maxWidth = Width::ThreeExtraLarge;

    public function getTitle(): string|Htmlable
    {
        return __('stable_panel.register.title');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('stable_panel.register.heading');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('stable_panel.register.subheading');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('stable_panel.register.owner_section'))
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        $this->getNameFormComponent()->placeholder(__('stable_panel.placeholders.name')),
                        $this->getEmailFormComponent()->placeholder(__('stable_panel.placeholders.email')),
                        TextInput::make('phone')
                            ->label(__('stable_panel.fields.mobile'))
                            ->helperText(__('account.phone_hint'))
                            ->placeholder(__('stable_panel.placeholders.mobile'))
                            ->tel()
                            ->required()
                            ->maxLength(20)
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                                $phone = PhoneNumber::normalize($value);

                                if ($phone === null || strlen($phone) < 10 || strlen($phone) > 15) {
                                    $fail(__('account.phone_invalid'));

                                    return;
                                }

                                if (User::query()->where('phone', $phone)->whereNotNull('phone_verified_at')->where('type', User::TYPE_STABLE_OWNER)->exists()) {
                                    $fail(__('stable_panel.register.phone_taken'));
                                }
                            })
                            ->suffixAction($this->sendCodeAction()),
                        TextInput::make('otp_code')
                            ->label(__('account.code_label'))
                            ->helperText(__('stable_panel.register.code_hint'))
                            ->placeholder(__('stable_panel.placeholders.code'))
                            ->required()
                            // a string: a code may start with 0, which a numeric field would drop
                            ->rule('digits:6')
                            ->extraInputAttributes(['inputmode' => 'numeric', 'autocomplete' => 'one-time-code']),
                        $this->getPasswordFormComponent()->placeholder(__('stable_panel.placeholders.password')),
                        $this->getPasswordConfirmationFormComponent()->placeholder(__('stable_panel.placeholders.password_confirmation')),
                    ]),
                Section::make(__('stable_panel.register.stable_section'))
                    ->icon('heroicon-o-home-modern')
                    ->columns(2)
                    ->schema([
                        ...StableProfileForm::names(),
                        ...StableProfileForm::location(),
                    ]),
                CaptchaField::make('captcha'),
            ])
            ->statePath('data');
    }

    protected function mutateFormDataBeforeRegister(#[SensitiveParameter] array $data): array
    {
        if ($error = $this->phoneCodeError($data['phone'] ?? null, $data['otp_code'] ?? null)) {
            throw ValidationException::withMessages(['data.otp_code' => $error]);
        }

        return $data;
    }

    protected function handleRegistration(#[SensitiveParameter] array $data): Model
    {
        return app(StableRegistration::class)->register(
            ['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'], 'password' => $data['password']],
            $data,
        );
    }
}

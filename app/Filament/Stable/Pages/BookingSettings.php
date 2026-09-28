<?php

namespace App\Filament\Stable\Pages;

use App\Enums\PaymentGateway;
use App\Models\Stable;
use App\Models\StablePaymentAccount;
use App\Services\Stables\SlotGenerator;
use App\Services\Stables\StablePaymentAccounts;
use App\Support\StableBookingSettings;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

/**
 * The stable's own booking rules: how far ahead it opens, cancellations, how customers may pay,
 * what to ask about each rider, how the owner hears of a booking, and whether it uses staff.
 */
class BookingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.stable.pages.booking-settings';

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.setup');
    }

    public static function getNavigationLabel(): string
    {
        return __('stable_panel.settings.title');
    }

    public function getTitle(): string
    {
        return __('stable_panel.settings.title');
    }

    public static function canAccess(): bool
    {
        $stable = Filament::getTenant();

        return $stable instanceof Stable && Gate::allows('update', $stable);
    }

    private function stable(): Stable
    {
        /** @var Stable $stable */
        $stable = Filament::getTenant();

        return $stable;
    }

    public function mount(): void
    {
        $settings = $this->stable()->bookingSettings()->toArray();
        $settings['allow_cancellation'] = $settings['cancellation_hours'] !== null;
        $settings['cancellation_hours'] ??= 24;
        $settings['payment_gateway'] ??= 'thawani';

        foreach ($this->stable()->paymentAccounts()->get() as $account) {
            $settings['accounts'][$account->gateway->value] = [
                'test_mode' => $account->test_mode,
                // secrets are never sent back to the browser: leaving one empty keeps it
                'credentials' => collect(StablePaymentAccount::FIELDS[$account->gateway->value])
                    ->map(fn (bool $secret, string $key) => $secret ? '' : $account->get($key))
                    ->all(),
            ];
        }

        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('stable_panel.settings.sections.window'))
                    ->icon('heroicon-o-calendar-days')
                    ->columns(2)
                    ->schema([
                        Select::make('horizon_days')
                            ->label(__('stable_panel.settings.horizon_days'))
                            ->helperText(__('stable_panel.settings.horizon_hint'))
                            ->options(collect([7, 14, 30, 60, 90, 120])
                                ->filter(fn (int $days) => $days <= (int) config('stable_bookings.max_horizon_days', 120))
                                ->mapWithKeys(fn (int $days) => [$days => trans_choice('stable_panel.units.days', $days, ['count' => $days])]))
                            ->required()
                            ->native(false),
                        Grid::make(1)->schema([
                            Toggle::make('allow_cancellation')
                                ->label(__('stable_panel.settings.allow_cancellation'))
                                ->live(),
                            TextInput::make('cancellation_hours')
                                ->label(__('stable_panel.settings.cancellation_hours'))
                                ->helperText(__('stable_panel.settings.cancellation_hint'))
                                ->suffix(__('stable_panel.units.hours_short'))
                                ->integer()
                                ->minValue(0)
                                ->maxValue(720)
                                ->required(fn (Get $get) => (bool) $get('allow_cancellation'))
                                ->visible(fn (Get $get) => (bool) $get('allow_cancellation')),
                        ])->columnSpan(1),
                    ]),
                Section::make(__('stable_panel.settings.sections.payment'))
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        CheckboxList::make('payment_options')
                            ->label(__('stable_panel.settings.payment_options'))
                            ->helperText(__('stable_panel.settings.payment_hint'))
                            ->options(collect(StableBookingSettings::PAYMENT_OPTIONS)->mapWithKeys(fn (string $o) => [$o => __('stable_panel.settings.payment.'.$o)]))
                            ->descriptions(collect(StableBookingSettings::PAYMENT_OPTIONS)->mapWithKeys(fn (string $o) => [$o => __('stable_panel.settings.payment_desc.'.$o)]))
                            ->required()
                            ->minItems(1),
                    ]),
                Section::make(__('stable_panel.payments.title'))
                    ->description(__('stable_panel.payments.description'))
                    ->icon('heroicon-o-building-library')
                    ->schema([
                        Radio::make('payment_mode')
                            ->hiddenLabel()
                            ->options([
                                'platform' => __('stable_panel.payments.modes.platform'),
                                'own' => __('stable_panel.payments.modes.own'),
                            ])
                            ->descriptions([
                                'platform' => __('stable_panel.payments.modes.platform_desc'),
                                'own' => __('stable_panel.payments.modes.own_desc'),
                            ])
                            ->required()
                            ->live(),
                        Grid::make(2)
                            ->visible(fn (Get $get) => $get('payment_mode') === 'own')
                            ->schema([
                                Select::make('payment_gateway')
                                    ->label(__('stable_panel.payments.gateway'))
                                    ->options(collect(StablePaymentAccount::gateways())->mapWithKeys(fn (string $g) => [$g => PaymentGateway::from($g)->label()]))
                                    ->required(fn (Get $get) => $get('payment_mode') === 'own')
                                    ->live()
                                    ->native(false),
                                Text::make(fn () => $this->accountStatusText())
                                    ->columnSpanFull(),
                                ...collect(StablePaymentAccount::FIELDS)->map(fn (array $fields, string $gateway) => Grid::make(2)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get) => $get('payment_gateway') === $gateway)
                                    ->schema([
                                        Toggle::make("accounts.{$gateway}.test_mode")
                                            ->label(__('stable_panel.payments.test_mode'))
                                            ->helperText(__('stable_panel.payments.test_mode_hint'))
                                            ->default(true)
                                            ->columnSpanFull(),
                                        ...collect($fields)->map(fn (bool $secret, string $key) => TextInput::make("accounts.{$gateway}.credentials.{$key}")
                                            ->label(__('stable_panel.payments.fields.'.$key))
                                            ->password($secret)
                                            ->revealable($secret)
                                            ->placeholder(fn () => $secret ? $this->maskedKey($gateway, $key) : null)
                                            ->helperText($secret ? __('stable_panel.payments.secret_hint') : null)
                                            ->maxLength(500)
                                            ->extraInputAttributes(['dir' => 'ltr', 'autocomplete' => 'off']))->values()->all(),
                                    ]))->values()->all(),
                            ]),
                    ]),
                Section::make(__('stable_panel.settings.sections.riders'))
                    ->description(__('stable_panel.settings.riders_hint'))
                    ->icon('heroicon-o-identification')
                    ->columns(2)
                    ->schema([
                        ...collect(StableBookingSettings::RIDER_FIELDS)->map(fn (string $field) => Grid::make(2)
                            ->columnSpan(1)
                            ->schema([
                                Toggle::make("rider_fields.{$field}.show")
                                    ->label(__('stable_panel.settings.rider.'.$field))
                                    ->live(),
                                Toggle::make("rider_fields.{$field}.required")
                                    ->label(__('stable_panel.settings.required'))
                                    ->disabled(fn (Get $get) => ! $get("rider_fields.{$field}.show")),
                            ]))->all(),
                        Textarea::make('waiver_text.en')
                            ->label(__('stable_panel.settings.waiver_en'))
                            ->rows(4)
                            ->required(fn (Get $get) => (bool) $get('rider_fields.waiver.show'))
                            ->visible(fn (Get $get) => (bool) $get('rider_fields.waiver.show')),
                        Textarea::make('waiver_text.ar')
                            ->label(__('stable_panel.settings.waiver_ar'))
                            ->rows(4)
                            ->extraInputAttributes(['dir' => 'rtl'])
                            ->visible(fn (Get $get) => (bool) $get('rider_fields.waiver.show')),
                    ]),
                Section::make(__('stable_panel.settings.sections.alerts'))
                    ->description(__('stable_panel.settings.alerts_hint'))
                    ->icon('heroicon-o-bell-alert')
                    ->columns(2)
                    ->schema([
                        CheckboxList::make('alert_channels')
                            ->label(__('stable_panel.settings.alert_channels'))
                            ->options(collect(StableBookingSettings::ALERT_CHANNELS)->mapWithKeys(fn (string $c) => [$c => __('stable_panel.settings.channels.'.$c)]))
                            ->columnSpanFull(),
                        Toggle::make('send_reminders')
                            ->label(__('stable_panel.settings.send_reminders'))
                            ->helperText(__('stable_panel.settings.send_reminders_hint'))
                            ->columnSpanFull(),
                        TextInput::make('alert_email')
                            ->label(__('stable_panel.settings.alert_email'))
                            ->helperText(__('stable_panel.settings.alert_email_hint'))
                            ->email(),
                        TextInput::make('alert_phone')
                            ->label(__('stable_panel.settings.alert_phone'))
                            ->helperText(__('stable_panel.settings.alert_phone_hint'))
                            ->tel(),
                    ]),
                Section::make(__('stable_panel.settings.sections.staff'))
                    ->icon('heroicon-o-users')
                    ->schema([
                        Toggle::make('staff_enabled')
                            ->label(__('stable_panel.settings.staff_enabled'))
                            ->helperText(__('stable_panel.settings.staff_hint')),
                    ]),
            ]);
    }

    private function maskedKey(string $gateway, string $key): ?string
    {
        $account = $this->stable()->paymentAccounts()->where('gateway', $gateway)->first();

        return $account && $account->get($key) !== '' ? $account->masked($key).' — '.__('stable_panel.payments.kept') : null;
    }

    private function accountStatusText(): string
    {
        $gateway = $this->data['payment_gateway'] ?? null;
        $account = $gateway ? $this->stable()->paymentAccounts()->where('gateway', $gateway)->first() : null;

        if (! $account) {
            return __('stable_panel.payments.status.none');
        }

        $text = __('stable_panel.payments.status.'.$account->status);

        if ($account->status === StablePaymentAccount::REJECTED && filled($account->review_note)) {
            $text .= ' '.__('stable_panel.fields.reason').': '.$account->review_note;
        }

        if ($account->last_tested_at) {
            $text .= ' · '.__('stable_panel.payments.last_test').': '.($account->last_test_ok ? '✓ ' : '✗ ').$account->last_test_message;
        }

        return $text;
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $stable = $this->stable();
        $before = $stable->bookingSettings()->horizonDays();

        $riderFields = collect(StableBookingSettings::RIDER_FIELDS)->mapWithKeys(function (string $field) use ($state) {
            $show = (bool) ($state['rider_fields'][$field]['show'] ?? false);

            return [$field => ['show' => $show, 'required' => $show && (bool) ($state['rider_fields'][$field]['required'] ?? false)]];
        })->all();

        $stable->forceFill(['booking_settings' => [
            'horizon_days' => (int) $state['horizon_days'],
            'cancellation_hours' => ($state['allow_cancellation'] ?? false) ? (int) $state['cancellation_hours'] : null,
            'payment_options' => array_values(array_intersect(StableBookingSettings::PAYMENT_OPTIONS, (array) ($state['payment_options'] ?? []))),
            'payment_mode' => in_array($state['payment_mode'] ?? null, StableBookingSettings::PAYMENT_MODES, true) ? $state['payment_mode'] : 'platform',
            'payment_gateway' => in_array($state['payment_gateway'] ?? null, StablePaymentAccount::gateways(), true) ? $state['payment_gateway'] : null,
            'alert_channels' => array_values(array_intersect(StableBookingSettings::ALERT_CHANNELS, (array) ($state['alert_channels'] ?? []))),
            'alert_email' => filled($state['alert_email'] ?? null) ? trim($state['alert_email']) : null,
            'alert_phone' => filled($state['alert_phone'] ?? null) ? trim($state['alert_phone']) : null,
            'staff_enabled' => (bool) ($state['staff_enabled'] ?? false),
            'send_reminders' => (bool) ($state['send_reminders'] ?? true),
            'rider_fields' => $riderFields,
            'waiver_text' => [
                'en' => filled($state['waiver_text']['en'] ?? null) ? $state['waiver_text']['en'] : null,
                'ar' => filled($state['waiver_text']['ar'] ?? null) ? $state['waiver_text']['ar'] : null,
            ],
        ]])->save();

        if ($stable->bookingSettings()->horizonDays() !== $before) {
            app(SlotGenerator::class)->syncStable($stable);
        }

        Notification::make()->success()->title(__('stable_panel.settings.saved'))->send();

        $gateway = $stable->bookingSettings()->paymentGateway();

        if ($stable->bookingSettings()->paymentMode() === 'own' && $gateway) {
            $previous = $stable->paymentAccounts()->where('gateway', $gateway)->first();
            $account = app(StablePaymentAccounts::class)->saveKeys(
                $stable->getKey(),
                PaymentGateway::from($gateway),
                (array) ($state['accounts'][$gateway]['credentials'] ?? []),
                (bool) ($state['accounts'][$gateway]['test_mode'] ?? true),
            );

            if (! $previous || $previous->updated_at?->ne($account->updated_at)) {
                Notification::make()
                    ->{$account->last_test_ok ? 'success' : 'warning'}()
                    ->title(__('stable_panel.payments.submitted'))
                    ->body($account->last_test_message)
                    ->persistent()
                    ->send();
            }
        }
    }
}

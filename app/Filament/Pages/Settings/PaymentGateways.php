<?php

namespace App\Filament\Pages\Settings;

use App\Enums\PaymentGateway;
use App\Models\SiteSetting;
use App\Support\SecretSetting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentGateways extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.settings');
    }

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.settings.payment-gateways';

    public ?array $data = [];

    public function getTitle(): string
    {
        return __('payment_gateways.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('payment_gateways.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'enabled_gateways' => $this->resolveEnabledGateways(),
            'thawani' => [
                'secret_key' => SecretSetting::get('thawani.secret_key'),
                'publishable_key' => SiteSetting::get('thawani.publishable_key', ''),
                'base_url' => SiteSetting::get('thawani.base_url', ''),
                'webhook_secret' => SecretSetting::get('thawani.webhook_secret'),
                'test_mode' => (bool) SiteSetting::get('thawani.test_mode', true),
            ],
            'nbo' => [
                'tranportal_id' => SiteSetting::get('nbo.tranportal_id', ''),
                'tranportal_password' => SecretSetting::get('nbo.tranportal_password'),
                'resource_key' => SecretSetting::get('nbo.resource_key'),
                'endpoint_url' => SiteSetting::get('nbo.endpoint_url', ''),
                'test_mode' => (bool) SiteSetting::get('nbo.test_mode', true),
            ],
            'ccavenue' => [
                'merchant_id' => SiteSetting::get('ccavenue.merchant_id', ''),
                'access_code' => SiteSetting::get('ccavenue.access_code', ''),
                'working_key' => SecretSetting::get('ccavenue.working_key'),
                'endpoint_url' => SiteSetting::get('ccavenue.endpoint_url', ''),
                'test_mode' => (bool) SiteSetting::get('ccavenue.test_mode', true),
            ],
            'vat' => [
                'enabled' => (bool) SiteSetting::get('vat.enabled', config('payments.vat.enabled', true)),
                'rate' => SiteSetting::get('vat.rate', config('payments.vat.rate', 5)),
                'registration_number' => SiteSetting::get('vat.registration_number', ''),
                'on_commission' => (bool) SiteSetting::get('vat.on_commission', false),
            ],
        ]);
    }

    /**
     * Reads the enabled_gateways JSON setting.
     */
    protected function resolveEnabledGateways(): array
    {
        $stored = SiteSetting::get('enabled_gateways');
        $decoded = $stored ? json_decode($stored, true) : null;

        $known = array_map(fn (PaymentGateway $gateway) => $gateway->value, PaymentGateway::cases());

        // Older installs also stored "free" and "cash" here; whether a service is free is now set per form.
        return is_array($decoded) ? array_values(array_intersect($decoded, $known)) : [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('payment_gateways.sections.active_gateway'))
                    ->description(__('payment_gateways.sections.active_gateway_desc'))
                    ->schema([
                        CheckboxList::make('enabled_gateways')
                            ->label(__('payment_gateways.options.active_gateway'))
                            ->options([
                                'thawani' => __('payment_gateways.options.thawani'),
                                'nbo' => __('payment_gateways.options.nbo'),
                                'ccavenue' => __('payment_gateways.options.ccavenue'),
                                'demo' => __('payment_gateways.options.demo'),
                            ])
                            ->descriptions(['demo' => __('payment_gateways.options.demo_desc')])
                            ->required()
                            ->live()
                            ->columns(2),
                    ]),

                Section::make(__('payment_gateways.sections.pricing'))
                    ->description(__('payment_gateways.sections.pricing_desc'))
                    ->schema([
                        Toggle::make('vat.enabled')
                            ->label(__('payment_gateways.fields.vat_enabled'))
                            ->default(true)
                            ->live(),

                        TextInput::make('vat.rate')
                            ->label(__('payment_gateways.fields.vat_rate'))
                            ->helperText(__('payment_gateways.fields.vat_rate_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(fn ($get) => (bool) $get('vat.enabled'))
                            ->visible(fn ($get) => (bool) $get('vat.enabled')),

                        Toggle::make('vat.on_commission')
                            ->label(__('payment_gateways.fields.vat_on_commission'))
                            ->helperText(__('payment_gateways.fields.vat_on_commission_helper'))
                            ->default(false)
                            ->visible(fn ($get) => (bool) $get('vat.enabled')),

                        TextInput::make('vat.registration_number')
                            ->label(__('payment_gateways.fields.vat_registration_number'))
                            ->helperText(__('payment_gateways.fields.vat_registration_number_helper'))
                            ->maxLength(50)
                            ->visible(fn ($get) => (bool) $get('vat.enabled')),
                    ]),

                Section::make(__('payment_gateways.sections.thawani'))
                    ->description(__('payment_gateways.sections.thawani_desc'))
                    ->visible(fn ($get) => in_array('thawani', (array) $get('enabled_gateways')))
                    ->schema([
                        Toggle::make('thawani.test_mode')
                            ->label(__('payment_gateways.fields.test_mode'))
                            ->helperText(__('payment_gateways.fields.thawani_test_mode_helper'))
                            ->default(true)
                            ->live(),

                        Group::make([
                            TextInput::make('thawani.secret_key')
                                ->label(__('payment_gateways.fields.secret_key'))
                                ->password()
                                ->revealable()
                                ->required(fn ($get) => in_array('thawani', (array) $get('enabled_gateways')))
                                ->maxLength(255),

                            TextInput::make('thawani.publishable_key')
                                ->label(__('payment_gateways.fields.publishable_key'))
                                ->required(fn ($get) => in_array('thawani', (array) $get('enabled_gateways')))
                                ->maxLength(255),
                        ])->columns(2),

                        TextInput::make('thawani.base_url')
                            ->label(__('payment_gateways.fields.base_url_override'))
                            ->helperText(__('payment_gateways.fields.url_override_helper'))
                            ->url()
                            ->placeholder(fn ($get) => $get('thawani.test_mode')
                                ? 'https://uatcheckout.thawani.om/api/v1'
                                : 'https://checkout.thawani.om/api/v1')
                            ->maxLength(255),

                        TextInput::make('thawani.webhook_secret')
                            ->label(__('payment_gateways.fields.webhook_secret'))
                            ->helperText(__('payment_gateways.fields.webhook_secret_helper').' '.__('payment_gateways.fields.webhook_url_helper', ['url' => route('payment.thawani.webhook')]))
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ]),

                Section::make(__('payment_gateways.sections.nbo'))
                    ->description(__('payment_gateways.sections.nbo_desc'))
                    ->visible(fn ($get) => in_array('nbo', (array) $get('enabled_gateways')))
                    ->schema([
                        Toggle::make('nbo.test_mode')
                            ->label(__('payment_gateways.fields.test_mode'))
                            ->helperText(__('payment_gateways.fields.nbo_test_mode_helper'))
                            ->default(true)
                            ->live(),

                        Group::make([
                            TextInput::make('nbo.tranportal_id')
                                ->label(__('payment_gateways.fields.tranportal_id'))
                                ->required(fn ($get) => in_array('nbo', (array) $get('enabled_gateways')))
                                ->maxLength(255),

                            TextInput::make('nbo.tranportal_password')
                                ->label(__('payment_gateways.fields.tranportal_password'))
                                ->password()
                                ->revealable()
                                ->required(fn ($get) => in_array('nbo', (array) $get('enabled_gateways')))
                                ->maxLength(255),
                        ])->columns(2),

                        TextInput::make('nbo.resource_key')
                            ->label(__('payment_gateways.fields.resource_key'))
                            ->helperText(__('payment_gateways.fields.resource_key_helper'))
                            ->password()
                            ->revealable()
                            ->required(fn ($get) => in_array('nbo', (array) $get('enabled_gateways')))
                            ->maxLength(255),

                        TextInput::make('nbo.endpoint_url')
                            ->label(__('payment_gateways.fields.endpoint_url_override'))
                            ->helperText(__('payment_gateways.fields.url_override_helper'))
                            ->url()
                            ->placeholder(fn ($get) => $get('nbo.test_mode')
                                ? 'https://unifiedpg.nbo.om/OLTPSTG/payment/hosted.htm'
                                : 'https://unifiedpg.nbo.om/OLTP/payment/hosted.htm')
                            ->maxLength(255),
                    ]),

                Section::make(__('payment_gateways.sections.ccavenue'))
                    ->description(__('payment_gateways.sections.ccavenue_desc'))
                    ->visible(fn ($get) => in_array('ccavenue', (array) $get('enabled_gateways')))
                    ->schema([
                        Toggle::make('ccavenue.test_mode')
                            ->label(__('payment_gateways.fields.test_mode'))
                            ->helperText(__('payment_gateways.fields.ccavenue_test_mode_helper'))
                            ->default(true)
                            ->live(),

                        Group::make([
                            TextInput::make('ccavenue.merchant_id')
                                ->label(__('payment_gateways.fields.merchant_id'))
                                ->required(fn ($get) => in_array('ccavenue', (array) $get('enabled_gateways')))
                                ->maxLength(255),

                            TextInput::make('ccavenue.access_code')
                                ->label(__('payment_gateways.fields.access_code'))
                                ->password()
                                ->revealable()
                                ->required(fn ($get) => in_array('ccavenue', (array) $get('enabled_gateways')))
                                ->maxLength(255),
                        ])->columns(2),

                        TextInput::make('ccavenue.working_key')
                            ->label(__('payment_gateways.fields.working_key'))
                            ->helperText(__('payment_gateways.fields.working_key_helper'))
                            ->password()
                            ->revealable()
                            ->required(fn ($get) => in_array('ccavenue', (array) $get('enabled_gateways')))
                            ->maxLength(255),

                        TextInput::make('ccavenue.endpoint_url')
                            ->label(__('payment_gateways.fields.endpoint_url_override'))
                            ->helperText(__('payment_gateways.fields.ccavenue_endpoint_url_helper'))
                            ->url()
                            ->placeholder(fn ($get) => $get('ccavenue.test_mode')
                                ? 'https://mti.bankmuscat.com:6443/transaction.do?command=initiateTransaction'
                                : 'https://smartpaytrns.bankmuscat.com/transaction.do?command=initiateTransaction')
                            ->maxLength(255),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $thawani = $state['thawani'] ?? [];
        $nbo = $state['nbo'] ?? [];
        $ccavenue = $state['ccavenue'] ?? [];
        $enabled = array_values($state['enabled_gateways'] ?? []);

        SiteSetting::set('enabled_gateways', json_encode($enabled), 'text', null, 'payment_gateways');

        SecretSetting::set('thawani.secret_key', $thawani['secret_key'] ?? '', 'payment_gateways');
        SiteSetting::set('thawani.publishable_key', $thawani['publishable_key'] ?? '', 'text', null, 'payment_gateways');
        SiteSetting::set('thawani.base_url', $thawani['base_url'] ?? '', 'text', null, 'payment_gateways');
        SecretSetting::set('thawani.webhook_secret', $thawani['webhook_secret'] ?? '', 'payment_gateways');
        SiteSetting::set('thawani.test_mode', ! empty($thawani['test_mode']), 'boolean', null, 'payment_gateways');

        SiteSetting::set('nbo.tranportal_id', $nbo['tranportal_id'] ?? '', 'text', null, 'payment_gateways');
        SecretSetting::set('nbo.tranportal_password', $nbo['tranportal_password'] ?? '', 'payment_gateways');
        SecretSetting::set('nbo.resource_key', $nbo['resource_key'] ?? '', 'payment_gateways');
        SiteSetting::set('nbo.endpoint_url', $nbo['endpoint_url'] ?? '', 'text', null, 'payment_gateways');
        SiteSetting::set('nbo.test_mode', ! empty($nbo['test_mode']), 'boolean', null, 'payment_gateways');

        SiteSetting::set('ccavenue.merchant_id', $ccavenue['merchant_id'] ?? '', 'text', null, 'payment_gateways');
        SiteSetting::set('ccavenue.access_code', $ccavenue['access_code'] ?? '', 'text', null, 'payment_gateways');
        SecretSetting::set('ccavenue.working_key', $ccavenue['working_key'] ?? '', 'payment_gateways');
        SiteSetting::set('ccavenue.endpoint_url', $ccavenue['endpoint_url'] ?? '', 'text', null, 'payment_gateways');
        SiteSetting::set('ccavenue.test_mode', ! empty($ccavenue['test_mode']), 'boolean', null, 'payment_gateways');

        $vat = $state['vat'] ?? [];

        SiteSetting::set('vat.enabled', ! empty($vat['enabled']), 'boolean', null, 'payment_gateways');
        SiteSetting::set('vat.on_commission', ! empty($vat['enabled']) && ! empty($vat['on_commission']), 'boolean', null, 'payment_gateways');
        SiteSetting::set('vat.registration_number', trim((string) ($vat['registration_number'] ?? '')), 'text', null, 'payment_gateways');

        if (! empty($vat['enabled'])) {
            SiteSetting::set('vat.rate', $vat['rate'] ?? config('payments.vat.rate', 5), 'number', null, 'payment_gateways');
        }

        Notification::make()
            ->title(__('payment_gateways.notifications.saved'))
            ->success()
            ->send();
    }
}

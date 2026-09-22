<?php

namespace App\Filament\Pages\Settings;

use App\Models\SiteSetting;
use App\Services\Auth\CustomerOtpService;
use App\Services\Sms\SmsManager;
use App\Support\SecretSetting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

/**
 * The SMS channel: which provider sends the messages (sign-in codes now, order notifications next)
 * and its credentials. The password is stored encrypted, like the payment gateway keys.
 */
class SmsSettings extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.settings');
    }

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.pages.settings.sms-settings';

    public ?array $data = [];

    public function getTitle(): string
    {
        return __('admin_sms_settings.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_sms_settings.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'driver' => SiteSetting::get('sms.driver', ''),
            'tamimah' => [
                'username' => SiteSetting::get('sms.tamimah.username', ''),
                'password' => SecretSetting::get('sms.tamimah.password'),
                'sender' => SiteSetting::get('sms.tamimah.sender', ''),
                'app_id' => SiteSetting::get('sms.tamimah.app_id', ''),
                'priority' => SiteSetting::get('sms.tamimah.priority', 0),
                'endpoint' => SiteSetting::get('sms.tamimah.endpoint', ''),
                'success_codes' => SiteSetting::get('sms.tamimah.success_codes', ''),
            ],
        ]);
    }

    /** @return array<string, string> */
    private function drivers(): array
    {
        return array_filter([
            SmsManager::TAMIMAH => __('admin_sms_settings.drivers.tamimah'),
            SmsManager::DEMO => __('admin_sms_settings.drivers.demo'),
            // the log driver writes one-time codes to the application log: not offered on a live site
            SmsManager::LOG => app()->isProduction() ? null : __('admin_sms_settings.drivers.log'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $isTamimah = fn ($get): bool => $get('driver') === SmsManager::TAMIMAH;

        return $schema
            ->components([
                Section::make(__('admin_sms_settings.sections.driver'))
                    ->description(__('admin_sms_settings.sections.driver_desc'))
                    ->schema([
                        Select::make('driver')
                            ->label(__('admin_sms_settings.fields.driver'))
                            ->options(fn () => $this->drivers())
                            ->placeholder(__('admin_sms_settings.drivers.none'))
                            ->native(false)
                            ->live(),

                        Text::make(__('admin_sms_settings.demo_warning'))
                            ->color('danger')
                            ->visible(fn ($get): bool => $get('driver') === SmsManager::DEMO),
                    ]),

                Section::make(__('admin_sms_settings.sections.tamimah'))
                    ->description(__('admin_sms_settings.sections.tamimah_desc'))
                    ->visible($isTamimah)
                    ->columns(2)
                    ->schema([
                        TextInput::make('tamimah.username')
                            ->label(__('admin_sms_settings.fields.username'))
                            ->required($isTamimah)
                            ->maxLength(255),

                        TextInput::make('tamimah.password')
                            ->label(__('admin_sms_settings.fields.password'))
                            ->password()
                            ->revealable()
                            ->required($isTamimah)
                            ->maxLength(255),

                        TextInput::make('tamimah.sender')
                            ->label(__('admin_sms_settings.fields.sender'))
                            ->helperText(__('admin_sms_settings.fields.sender_helper'))
                            ->required($isTamimah)
                            ->maxLength(30),

                        TextInput::make('tamimah.app_id')
                            ->label(__('admin_sms_settings.fields.app_id'))
                            ->maxLength(255),

                        TextInput::make('tamimah.priority')
                            ->label(__('admin_sms_settings.fields.priority'))
                            ->numeric()
                            ->integer()
                            ->default(0),

                        TextInput::make('tamimah.success_codes')
                            ->label(__('admin_sms_settings.fields.success_codes'))
                            ->helperText(__('admin_sms_settings.fields.success_codes_helper'))
                            ->maxLength(255),

                        TextInput::make('tamimah.endpoint')
                            ->label(__('admin_sms_settings.fields.endpoint'))
                            ->helperText(__('admin_sms_settings.fields.endpoint_helper'))
                            ->url()
                            ->placeholder('https://tamimahsms.com/user/bulkpush.asmx')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $tamimah = $state['tamimah'] ?? [];

        SiteSetting::set('sms.driver', $state['driver'] ?? '', 'text', null, 'sms');

        // the section is hidden (and its state dropped) under another driver: keep what was saved then
        if (($state['driver'] ?? null) === SmsManager::TAMIMAH) {
            SiteSetting::set('sms.tamimah.username', $tamimah['username'] ?? '', 'text', null, 'sms');
            SecretSetting::set('sms.tamimah.password', $tamimah['password'] ?? '', 'sms');
            SiteSetting::set('sms.tamimah.sender', $tamimah['sender'] ?? '', 'text', null, 'sms');
            SiteSetting::set('sms.tamimah.app_id', $tamimah['app_id'] ?? '', 'text', null, 'sms');
            SiteSetting::set('sms.tamimah.priority', (int) ($tamimah['priority'] ?? 0), 'number', null, 'sms');
            SiteSetting::set('sms.tamimah.endpoint', $tamimah['endpoint'] ?? '', 'text', null, 'sms');
            SiteSetting::set('sms.tamimah.success_codes', $tamimah['success_codes'] ?? '', 'text', null, 'sms');
        }

        Notification::make()->title(__('admin_sms_settings.notifications.saved'))->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')
                ->label(__('admin_sms_settings.actions.test'))
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->modalHeading(__('admin_sms_settings.actions.test'))
                ->modalDescription(__('admin_sms_settings.actions.test_desc'))
                ->schema([
                    TextInput::make('phone')
                        ->label(__('admin_sms_settings.fields.test_phone'))
                        ->tel()
                        ->required()
                        ->placeholder('9123 4567'),
                ])
                ->action(function (array $data): void {
                    $phone = app(CustomerOtpService::class)->normalize($data['phone'] ?? null);
                    $sms = app(SmsManager::class);

                    if (! $phone) {
                        Notification::make()->title(__('admin_sms_settings.notifications.bad_phone'))->danger()->send();

                        return;
                    }

                    $result = $sms->send($phone, __('admin_sms_settings.test_message', ['site' => SiteSetting::siteName()]), 'test');

                    $result->ok
                        ? Notification::make()->title($sms->isDemo() ? __('admin_sms_settings.notifications.demo_sent') : __('admin_sms_settings.notifications.test_sent'))->success()->send()
                        : Notification::make()->title(__('admin_sms_settings.notifications.test_failed'))->body((string) $result->error)->danger()->persistent()->send();
                }),
        ];
    }
}

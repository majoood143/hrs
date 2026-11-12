<?php

namespace App\Filament\Pages;

use App\Filament\Support\SecretInput;
use App\Mail\TestSmtpEmail;
use App\Models\SiteSetting;
use App\Support\MailServer;
use App\Support\SecretSetting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** The SMTP server the site's emails go through (`Support\MailServer`). */
class SmtpSettings extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected string $view = 'filament.pages.smtp-settings';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.settings');
    }

    public function getTitle(): string
    {
        return __('admin_smtp_settings.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_smtp_settings.navigation_label');
    }

    public function mount(): void
    {
        // the password opens empty: it never goes to the browser
        $this->form->fill(['password' => ''] + MailServer::settings());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin_smtp_settings.section'))
                    ->icon('heroicon-o-server')
                    ->schema([
                        TextInput::make('host')->label(__('admin_smtp_settings.fields.host'))->required()->maxLength(255),
                        TextInput::make('port')->label(__('admin_smtp_settings.fields.port'))->numeric()->required(),
                        TextInput::make('username')->label(__('admin_smtp_settings.fields.username'))->required()->maxLength(255),
                        SecretInput::make('password', 'smtp.password')->label(__('admin_smtp_settings.fields.password')),
                        Select::make('encryption')
                            ->label(__('admin_smtp_settings.fields.encryption'))
                            ->options(['tls' => 'TLS', 'ssl' => 'SSL', '' => __('admin_smtp_settings.fields.encryption_none')])
                            ->native(false),
                        TextInput::make('from_address')->label(__('admin_smtp_settings.fields.from_address'))->email()->required()->maxLength(255),
                        TextInput::make('from_name')->label(__('admin_smtp_settings.fields.from_name'))->required()->maxLength(255),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (['host', 'port', 'username', 'encryption', 'from_address', 'from_name'] as $key) {
            SiteSetting::set("smtp.{$key}", (string) ($state[$key] ?? ''), 'text', null, 'smtp');
        }
        SecretSetting::setUnlessBlank('smtp.password', $state['password'] ?? '', 'smtp');

        Notification::make()->title(__('admin_smtp_settings.notifications.saved'))->success()->send();
    }

    /** Sends a test email to the "from" address with what is in the form (a blank password = the saved one). */
    public function testConnection(): void
    {
        $state = $this->form->getState();
        $state['password'] = filled($state['password'] ?? null) ? $state['password'] : SecretSetting::get('smtp.password');

        try {
            MailServer::apply(array_map('strval', $state));
            Mail::mailer('smtp')->to($state['from_address'])->send(new TestSmtpEmail);

            Notification::make()->title(__('admin_smtp_settings.notifications.test_sent'))->success()->send();
        } catch (Throwable $e) {
            Log::warning('SMTP test failed: '.$e->getMessage());
            Notification::make()->title(__('admin_smtp_settings.notifications.test_failed'))->body($e->getMessage())->danger()->persistent()->send();
        }
    }
}

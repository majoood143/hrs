<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Exception;
use App\Mail\TestSmtpEmail;
use Filament\Pages\Page;
use App\Services\SettingsService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SmtpSettings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.smtp-settings';

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public static function getNavigationGroup(): string | \UnitEnum | null
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

    public function mount(SettingsService $settings): void
    {
        $smtpSettings = $settings->getSmtpSettings();

        $this->form->fill([
            'mail_host' => $smtpSettings['host'],
            'mail_port' => $smtpSettings['port'],
            'mail_username' => $smtpSettings['username'],
            'mail_password' => $smtpSettings['password'],
            'mail_encryption' => $smtpSettings['encryption'],
            'mail_from_address' => $smtpSettings['from_address'],
            'mail_from_name' => $smtpSettings['from_name'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('SMTP Configuration')
                    ->schema([
                        TextInput::make('mail_host')
                            ->label('SMTP Host')
                            ->required(),
                        TextInput::make('mail_port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->required(),
                        TextInput::make('mail_username')
                            ->label('SMTP Username')
                            ->required(),
                        TextInput::make('mail_password')
                            ->label('SMTP Password')
                            ->password()
                            ->required(),
                        Select::make('mail_encryption')
                            ->label('Encryption')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                '' => 'None',
                            ]),
                    ])->columns(2),

                Section::make('From Address')
                    ->schema([
                        TextInput::make('mail_from_address')
                            ->label('From Email')
                            ->email()
                            ->required(),
                        TextInput::make('mail_from_name')
                            ->label('From Name')
                            ->required(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }


    public function save(SettingsService $settings): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $settings->set($key, $value);
        }

        Notification::make()
            ->title('SMTP settings saved successfully!')
            ->success()
            ->send();
    }

    public function testConnection(): void
    {
        try {
            $data = $this->form->getState();
            if (empty($data['mail_host']) || empty($data['mail_username']) || empty($data['mail_from_address'])) {
                throw new Exception('Required SMTP settings are missing.');
            }
            if (!filter_var($data['mail_from_address'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid from email address.');
            }

            config([
                'mail.mailers.smtp.host' => $data['mail_host'],
                'mail.mailers.smtp.port' => $data['mail_port'] ?? 587,
                'mail.mailers.smtp.username' => $data['mail_username'],
                'mail.mailers.smtp.password' => $data['mail_password'] ?? '',
                'mail.mailers.smtp.encryption' => $data['mail_encryption'] ?? null,
                'mail.from.address' => $data['mail_from_address'],
                'mail.from.name' => $data['mail_from_name'] ?? 'Test User',
            ]);

            Mail::to($data['mail_from_address'], $data['mail_from_name'] ?? 'Test User')
                ->send(new TestSmtpEmail());

            Notification::make()
                ->title('SMTP connection test successful!')
                ->success()
                ->send();
        } catch (Exception $e) {
            Log::error('SMTP test failed: ' . $e->getMessage());
            Notification::make()
                ->title('SMTP connection failed. Please check your settings.' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

}

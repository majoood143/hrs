<?php

namespace App\Providers;

use Exception;
use Illuminate\Support\ServiceProvider;

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Checks\Checks\PingCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use App\Services\SettingsService;
use App\Support\ImageCompressor;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Illuminate\Support\Facades\Gate;
use App\Policies\FormPolicy;
use Packstub\FormBuilder\Models\Form as FormBuilderForm;



class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(SettingsService::class, function () {
            return new SettingsService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(SettingsService $settings): void
    {
        //
        Gate::define('viewApiDocs', function ($user) {
            return $user?->hasRole('super_admin') ?? false;
        });

        Gate::policy(FormBuilderForm::class, FormPolicy::class);

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(array_keys(config('languages.available', ['en' => [], 'ar' => []])))
                ->labels([
                    'en' => 'English',
                    'ar' => 'العربية',
                ])
                ->visible(insidePanels: true, outsidePanels: true)
                ->excludes(['public']);
        });

        FileUpload::configureUsing(function (FileUpload $fileUpload) {
            $fileUpload->saveUploadedFileUsing(function (FileUpload $component, TemporaryUploadedFile $file) {
                $path = $component->saveUploadedFile($file);

                if ($path) {
                    ImageCompressor::compress($component->getDisk()->path($path));
                }

                return $path;
            });
        });

        Event::listen(MediaHasBeenAddedEvent::class, function (MediaHasBeenAddedEvent $event) {
            ImageCompressor::compress($event->media->getPath());
        });

        Health::checks([
            OptimizedAppCheck::new(),
            DebugModeCheck::new(),
            EnvironmentCheck::new(),
            UsedDiskSpaceCheck::new(),
            PingCheck::new()->url('https://www.google.com'),
            QueueCheck::new(),
            DatabaseCheck::new(),
        ]);


        try {
            if (Schema::hasTable('settings')) {
                //$settings = app(Setting::class);
                $smtpSettings = $settings->getSmtpSettings();

                // Only override if we have valid settings
                if (!empty($smtpSettings['host']) && !empty($smtpSettings['username'])) {
                    Config::set('mail.mailers.smtp.host', $smtpSettings['host']);
                    Config::set('mail.mailers.smtp.port', $smtpSettings['port']);
                    Config::set('mail.mailers.smtp.username', $smtpSettings['username']);
                    Config::set('mail.mailers.smtp.password', $smtpSettings['password']);
                    Config::set('mail.mailers.smtp.encryption', $smtpSettings['encryption']);
                    Config::set('mail.from.address', $smtpSettings['from_address']);
                    Config::set('mail.from.name', $smtpSettings['from_name']);
                }
            }
        } catch (Exception $e) {
            // Settings table might not exist during installation
            // Or there might be a database connection issue
        }
    }
}

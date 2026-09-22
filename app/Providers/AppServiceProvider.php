<?php

namespace App\Providers;

use App\Filament\Support\FormPaymentTab;
use App\Models\Setting;
use App\Models\SiteSetting;
use App\Policies\ActivityPolicy;
use App\Policies\FormPolicy;
use App\Policies\TagPolicy;
use App\Policies\TokenPolicy;
use App\Services\SettingsService;
use App\Support\FormOrderSettings;
use App\Support\ImageCompressor;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Exception;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Packstub\FormBuilder\Facades\FormBuilder;
use Packstub\FormBuilder\Models\Form as FormBuilderForm;
use Rupadana\ApiService\Models\Token;
use Spatie\Activitylog\Models\Activity;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\PingCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\Tags\Tag;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
        $this->app->singleton(SettingsService::class, function () {
            return new SettingsService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(SettingsService $settings): void
    {
        // SiteSetting memoizes the settings table in a static property for the life of the PHP
        // process (see SiteSetting::cached()); reset it at the start of each request/boot so a
        // long-lived worker (Octane, queue, the test suite rebuilding the app per test) never
        // serves a previous request's settings.
        SiteSetting::resetMemo();

        Gate::define('viewApiDocs', function ($user) {
            return $user?->hasRole('super_admin') ?? false;
        });

        Gate::policy(FormBuilderForm::class, FormPolicy::class);

        // Overrides the vendor policy, which checks Shield's default snake_case
        // permission names instead of this project's ViewAny:Model format.
        Gate::policy(Token::class, TokenPolicy::class);

        // Tag (CmsTagResource) and Activity (the Activity Log page) are vendor models
        // outside app/Models, so Laravel's convention-based policy discovery never finds
        // their Shield-generated policies; without this they're reachable only by
        // super_admin and no role can be granted control over them.
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Forms linked to a service turn their submissions into (paid) orders.
        FormBuilder::registerFormTab(fn () => FormPaymentTab::make());
        FormBuilder::closedWhen(function (FormBuilderForm $form): ?string {
            $orders = FormOrderSettings::for($form);

            return $orders->hasService() && ! $orders->service()?->is_active ? __('orders.service_unavailable') : null;
        });
        FormBuilder::beforeForm(fn (FormBuilderForm $form): string => FormOrderSettings::for($form)->priceNoticeHtml());
        FormBuilder::holdNotificationsWhen(fn (FormBuilderForm $form): bool => FormOrderSettings::for($form)->heldUntilPaid());

        // The listeners in app/Listeners (CreateOrderFromSubmission, NotifyOnOrderReceived, NotifyOnOrderCompleted)
        // are found by Laravel's own event discovery: registering them here as well would run each one twice.

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
                // $settings = app(Setting::class);
                $smtpSettings = $settings->getSmtpSettings();

                // Only override if we have valid settings
                if (! empty($smtpSettings['host']) && ! empty($smtpSettings['username'])) {
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

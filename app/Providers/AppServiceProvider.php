<?php

namespace App\Providers;

use App\Filament\Forms\RacePredictionActions;
use App\Filament\Forms\SubmissionPdfActions;
use App\Filament\Forms\ViewFormInsights;
use App\Filament\RichEditor\TextDirectionPlugin;
use App\Filament\Support\FormPaymentTab;
use App\Http\Controllers\Api\V1\Owner\OwnerAuthController;
use App\Models\Setting;
use App\Models\SiteSetting;
use App\Policies\ActivityPolicy;
use App\Policies\FormPolicy;
use App\Policies\TagPolicy;
use App\Policies\TokenPolicy;
use App\Policies\WhatsappInstancePolicy;
use App\Policies\WhatsappMessagePolicy;
use App\Policies\WhatsappWebhookPolicy;
use App\Services\Forms\RacePredictions;
use App\Services\SettingsService;
use App\Support\Api\PostingToken;
use App\Support\FormOrderSettings;
use App\Support\ImageCompressor;
use App\Support\PhoneNumber;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Dedoc\Scramble\Scramble;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
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
use WallaceMartinss\FilamentEvolution\Models\WhatsappInstance;
use WallaceMartinss\FilamentEvolution\Models\WhatsappMessage;
use WallaceMartinss\FilamentEvolution\Models\WhatsappWebhook;

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

        // Each form's Insights page (charts of its submissions). Registered here, not in boot(): Filament
        // loads its panel routes while the package providers boot, which is before this provider's boot().
        FormBuilder::registerResourcePage('insights', ViewFormInsights::class, '/{record}/insights');
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

        // The rich editor's text-direction extension (npm run build:filament-plugins,
        // then php artisan filament:assets copies it to public/js/app).
        FilamentAsset::register([
            Js::make(TextDirectionPlugin::ASSET, resource_path('js/dist/filament/rich-editor/text-direction.js'))
                ->loadedOnRequest(),
        ]);

        Gate::define('viewApiDocs', function ($user) {
            return $user?->hasRole('super_admin') ?? false;
        });

        // The mobile app's public API (/api/v1): per device IP, generous enough for scrolling lists.
        RateLimiter::for('api-public', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        // Asking for and checking codes, and the owners' password sign-in: per address (roomy: a mobile
        // carrier puts many phones behind one) and per phone or email, which no change of address resets.
        // The codes themselves have their own limits too (CustomerOtpService).
        RateLimiter::for('api-codes', function (Request $request) {
            $who = PhoneNumber::normalize((string) $request->input('phone')) ?? mb_strtolower(trim((string) $request->input('email')));

            return array_filter([
                Limit::perMinute(60)->by('ip|'.$request->ip()),
                $who !== '' ? Limit::perMinute(10)->by('who|'.$who) : null,
            ]);
        });

        // Posting from the app: per address, and per verified phone (its posting token).
        RateLimiter::for('api-posting', function (Request $request) {
            $phone = PostingToken::phone($request->input('posting_token'));

            return array_filter([
                Limit::perMinute(30)->by('ip|'.$request->ip()),
                $phone !== null ? Limit::perMinute(10)->by('phone|'.$phone) : null,
            ]);
        });

        // A signed-in customer's or stable owner's changes (booking, cancelling, closing times…): per
        // account, after auth (the address only when there is somehow no user).
        RateLimiter::for('api-account', fn (Request $request) => Limit::perMinute(20)
            ->by(($request->user('customer_api') ? 'customer|'.$request->user('customer_api')->getKey() : 'ip|'.$request->ip())));
        RateLimiter::for('api-owner', fn (Request $request) => Limit::perMinute(60)
            ->by(($request->user('owner_api') ? 'owner|'.$request->user('owner_api')->getKey() : 'ip|'.$request->ip())));

        // Racing search asks the source each time; view counts are cheap but should not be inflated.
        RateLimiter::for('api-racing-search', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('api-views', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // A stable owner's app token is a User token, which the admin API's guard would accept too:
        // it opens the owner endpoints only.
        Sanctum::authenticateAccessTokensUsing(fn (PersonalAccessToken $token, bool $isValid): bool => $isValid
            && (! in_array(OwnerAuthController::ABILITY, (array) $token->abilities, true) || request()->is('api/v1/owner/*')));

        // Its own docs (/docs/v1, same access as /docs/api), so the app's types follow only its endpoints.
        Scramble::registerApi('v1', [
            'api_path' => 'api/v1',
            'info' => [
                'version' => '1',
                'description' => 'The Muhra mobile app API: public and read-only. Send `Accept-Language: en|ar`; every text comes back in that language.',
            ],
        ])
            // The group's catch-all only turns unknown paths into a JSON 404; it is not an endpoint.
            ->routes(fn ($route) => str_starts_with($route->uri(), 'api/v1/') && $route->getName() !== 'api.v1.not-found')
            ->expose(ui: 'docs/v1', document: 'docs/v1.json');

        // The admin API (rupadana/filament-api-service) registers `api/{panel}/…` with any value
        // allowed for {panel}, so `api/v1/farriers` would hit the admin route (401). {panel} only
        // ever means a Filament panel id: say so once every route is registered.
        $this->app->booted(function () {
            $panels = implode('|', array_map(fn (string $id) => preg_quote($id, '/'), array_keys(Filament::getPanels())));

            foreach (Route::getRoutes()->getRoutes() as $route) {
                if ($panels !== '' && in_array('panel', $route->parameterNames(), true)) {
                    $route->where('panel', $panels);
                }
            }
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

        // Same for the WhatsApp plugin's models (Evolution API instances, messages, webhooks).
        Gate::policy(WhatsappInstance::class, WhatsappInstancePolicy::class);
        Gate::policy(WhatsappMessage::class, WhatsappMessagePolicy::class);
        Gate::policy(WhatsappWebhook::class, WhatsappWebhookPolicy::class);

        // Forms linked to a service turn their submissions into (paid) orders.
        FormBuilder::registerFormTab(fn () => FormPaymentTab::make());
        FormBuilder::closedWhen(function (FormBuilderForm $form): ?string {
            $orders = FormOrderSettings::for($form);

            return $orders->hasService() && ! $orders->service()?->is_active ? __('orders.service_unavailable') : null;
        });
        FormBuilder::closedWhen(fn (FormBuilderForm $form): ?string => RacePredictions::waitingForRunners($form) ? __('race_predictions.waiting_for_runners') : null);
        FormBuilder::beforeForm(fn (FormBuilderForm $form): string => FormOrderSettings::for($form)->priceNoticeHtml());
        FormBuilder::holdNotificationsWhen(fn (FormBuilderForm $form): bool => FormOrderSettings::for($form)->heldUntilPaid());

        // The way to each form's Insights page (registered in register()), from the forms list and the editor.
        FormBuilder::registerRecordAction(fn () => Action::make('insights')
            ->label(__('admin_form_insights.action'))
            ->icon('heroicon-o-chart-pie')
            ->color('gray')
            ->url(fn (FormBuilderForm $record): string => ViewFormInsights::getUrl(['record' => $record]))
            ->visible(fn (FormBuilderForm $record): bool => ViewFormInsights::canAccess(['record' => $record])));

        // Race prediction forms: start one from a race day, reload the runners of one.
        FormBuilder::registerCreateAction(fn () => RacePredictionActions::create());
        FormBuilder::registerRecordAction(fn () => RacePredictionActions::reload());

        // "PDF" (per language) in each form's submissions menus: all as filtered, the selected ones, one.
        FormBuilder::registerSubmissionActions(SubmissionPdfActions::for(...));

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

        // Phone fields (->tel()) accept what an Arabic keyboard or a pasted contact gives:
        // Arabic-Indic digits and hidden direction marks failed Filament's ASCII-only phone regex.
        TextInput::configureUsing(function (TextInput $input) {
            $toAscii = fn (TextInput $component, $state) => $component->isTel() && is_string($state)
                ? PhoneNumber::toAscii($state)
                : $state;

            $input->mutateStateForValidationUsing($toAscii)->mutateDehydratedStateUsing($toAscii);
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

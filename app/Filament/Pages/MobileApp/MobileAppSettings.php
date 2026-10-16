<?php

namespace App\Filament\Pages\MobileApp;

use App\Support\MobileApp\AppClient;
use App\Support\MobileApp\AppLinks;
use App\Support\MobileApp\AppVersion;
use App\Support\MobileApp\MobileFeatures;
use App\Support\MobileApp\MobileImages;
use App\Support\MobileApp\MobileMaintenance;
use App\Support\MobileApp\MobilePosting;
use App\Support\MobileApp\MobileUpdates;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * What the Muhra app shows: feature switches, the versions that must / may update, maintenance, and
 * how posting from the app is moderated, the photos of the Services screen, and the keys that let website links open the app.
 * Read by the app from GET /api/v1/app-status. Every save is written to the activity log
 * (log name `mobile_app`) with the values before and after.
 */
class MobileAppSettings extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'mobile-app/settings';

    protected string $view = 'filament.pages.mobile-app.settings';

    public ?array $data = [];

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.mobile_app');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_mobile_app.settings.title');
    }

    public function getTitle(): string
    {
        return __('admin_mobile_app.settings.title');
    }

    public function mount(): void
    {
        $this->form->fill(self::currentState());
    }

    /** @return array{features: array<string, mixed>, updates: array<string, mixed>, maintenance: array<string, mixed>, posting: array<string, mixed>, images: array<string, string|null>, links: array<string, mixed>} */
    private static function currentState(): array
    {
        return [
            'features' => MobileFeatures::rules(),
            'updates' => MobileUpdates::settings(),
            'maintenance' => MobileMaintenance::settings(),
            'posting' => MobilePosting::settings(),
            'images' => MobileImages::settings(),
            'links' => ['android_fingerprints' => implode("\n", AppLinks::settings()['android_fingerprints'])] + AppLinks::settings(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('mobile_app')
                ->persistTabInQueryString()
                ->tabs([
                    Tab::make(__('admin_mobile_app.tabs.features'))
                        ->icon('heroicon-o-squares-2x2')
                        ->schema($this->featureSections()),
                    Tab::make(__('admin_mobile_app.tabs.updates'))
                        ->icon('heroicon-o-arrow-path')
                        ->schema($this->updateSections()),
                    Tab::make(__('admin_mobile_app.tabs.maintenance'))
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->schema($this->maintenanceSections()),
                    Tab::make(__('admin_mobile_app.tabs.posting'))
                        ->icon('heroicon-o-shield-check')
                        ->schema($this->postingSections()),
                    Tab::make(__('admin_mobile_app.tabs.images'))
                        ->icon('heroicon-o-photo')
                        ->schema($this->imageSections()),
                    Tab::make(__('admin_mobile_app.tabs.links'))
                        ->icon('heroicon-o-link')
                        ->schema($this->linkSections()),
                ]),
        ])->statePath('data');
    }

    /** @return list<Section> */
    private function featureSections(): array
    {
        $sections = [
            Text::make(__('admin_mobile_app.features.intro')),
        ];

        foreach (MobileFeatures::GROUPS as $group => $keys) {
            $sections[] = Section::make(__('admin_mobile_app.groups.'.$group))
                ->collapsible()
                ->schema(array_map(fn (string $key) => Grid::make(['default' => 1, 'md' => 4])->schema([
                    Toggle::make("features.{$key}.enabled")
                        ->label(__('admin_mobile_app.features.'.$key))
                        ->helperText(__('admin_mobile_app.features.'.$key.'_help'))
                        ->inline(false),
                    CheckboxList::make("features.{$key}.platforms")
                        ->label(__('admin_mobile_app.fields.platforms'))
                        ->options(self::platformOptions())
                        ->columns(2)
                        ->required(),
                    TextInput::make("features.{$key}.min_version")
                        ->label(__('admin_mobile_app.fields.feature_min_version'))
                        ->placeholder('1.0.0')
                        ->regex(AppVersion::PATTERN)
                        ->maxLength(14),
                    Select::make("features.{$key}.when_off")
                        ->label(__('admin_mobile_app.fields.when_off'))
                        ->options([
                            MobileFeatures::HIDDEN => __('admin_mobile_app.when_off.hidden'),
                            MobileFeatures::SOON => __('admin_mobile_app.when_off.soon'),
                        ])
                        ->selectablePlaceholder(false)
                        ->required(),
                ]), $keys));
        }

        return $sections;
    }

    /** @return list<Section> */
    private function updateSections(): array
    {
        $versionFields = fn (string $platform) => [
            TextInput::make("updates.{$platform}.latest_version")
                ->label(__('admin_mobile_app.fields.latest_version'))
                ->helperText(__('admin_mobile_app.fields.latest_version_help'))
                ->placeholder('1.0.0')
                ->regex(AppVersion::PATTERN)
                ->maxLength(14),
            TextInput::make("updates.{$platform}.min_version")
                ->label(__('admin_mobile_app.fields.min_version'))
                ->helperText(__('admin_mobile_app.fields.min_version_help'))
                ->placeholder('1.0.0')
                ->regex(AppVersion::PATTERN)
                ->maxLength(14)
                ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get, $platform): void {
                    $latest = trim((string) $get("updates.{$platform}.latest_version"));

                    if (AppVersion::valid((string) $value) && AppVersion::valid($latest) && AppVersion::lessThan($latest, (string) $value)) {
                        $fail(__('admin_mobile_app.validation.min_above_latest'));
                    }
                }),
        ];

        $messages = fn (string $kind) => Grid::make(count(MobileUpdates::locales()))->schema(array_map(
            fn (string $locale) => Textarea::make("updates.messages.{$kind}.{$locale}")
                ->label(__('admin_mobile_app.fields.message_'.$kind).' ('.config("languages.available.{$locale}.name", $locale).')')
                ->placeholder(__('api.app.update_'.$kind, [], $locale))
                ->rows(2)
                ->maxLength(300)
                ->extraInputAttributes($locale === 'ar' ? ['dir' => 'rtl'] : []),
            MobileUpdates::locales(),
        ));

        return [
            Text::make(__('admin_mobile_app.updates.intro')),
            Section::make(__('admin_mobile_app.platforms.ios'))
                ->icon('heroicon-o-device-phone-mobile')
                ->columns(3)
                ->schema([
                    ...$versionFields('ios'),
                    TextInput::make('updates.ios.app_store_id')
                        ->label(__('admin_mobile_app.fields.app_store_id'))
                        ->helperText(__('admin_mobile_app.fields.app_store_id_help'))
                        ->numeric()
                        ->maxLength(15),
                ]),
            Section::make(__('admin_mobile_app.platforms.android'))
                ->icon('heroicon-o-device-phone-mobile')
                ->columns(3)
                ->schema([
                    ...$versionFields('android'),
                    Text::make(fn () => __('admin_mobile_app.updates.play_link', ['url' => MobileUpdates::storeUrls('android')[0]])),
                ]),
            Section::make(__('admin_mobile_app.updates.messages'))
                ->description(__('admin_mobile_app.updates.messages_help'))
                ->collapsible()
                ->collapsed()
                ->schema([$messages(MobileUpdates::OPTIONAL), $messages(MobileUpdates::REQUIRED)]),
        ];
    }

    /** @return list<Section> */
    private function maintenanceSections(): array
    {
        return [
            Section::make(__('admin_mobile_app.tabs.maintenance'))
                ->description(__('admin_mobile_app.maintenance.help'))
                ->schema([
                    Toggle::make('maintenance.enabled')
                        ->label(__('admin_mobile_app.fields.maintenance_enabled'))
                        ->onColor('danger'),
                    Grid::make(count(MobileUpdates::locales()))->schema(array_map(
                        fn (string $locale) => Textarea::make("maintenance.messages.{$locale}")
                            ->label(__('admin_mobile_app.fields.maintenance_message').' ('.config("languages.available.{$locale}.name", $locale).')')
                            ->placeholder(__('api.app.maintenance', [], $locale))
                            ->rows(3)
                            ->maxLength(500)
                            ->extraInputAttributes($locale === 'ar' ? ['dir' => 'rtl'] : []),
                        MobileUpdates::locales(),
                    )),
                ]),
        ];
    }

    /** @return list<Section> */
    private function postingSections(): array
    {
        return [
            Section::make(__('admin_mobile_app.tabs.posting'))
                ->description(__('admin_mobile_app.posting.help'))
                ->schema([
                    Toggle::make('posting.review')
                        ->label(__('admin_mobile_app.fields.posting_review'))
                        ->helperText(__('admin_mobile_app.fields.posting_review_help')),
                    TextInput::make('posting.daily_limit')
                        ->label(__('admin_mobile_app.fields.posting_daily_limit'))
                        ->helperText(__('admin_mobile_app.fields.posting_daily_limit_help'))
                        ->integer()
                        ->minValue(1)
                        ->maxValue(MobilePosting::MAX_DAILY_LIMIT)
                        ->required(),
                ]),
        ];
    }

    /** @return list<Section> */
    private function imageSections(): array
    {
        return [
            Section::make(__('admin_mobile_app.tabs.images'))
                ->description(__('admin_mobile_app.images.help'))
                ->columns(['default' => 1, 'md' => 3])
                ->schema(array_map(fn (string $key) => FileUpload::make("images.{$key}")
                    ->label(__('admin_mobile_app.images.'.$key))
                    ->helperText(__('admin_mobile_app.images.'.$key.'_help'))
                    ->image()
                    ->imageEditor()
                    ->disk('public')
                    ->directory(MobileImages::DIRECTORY)
                    ->visibility('public')
                    ->maxSize(5120)
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth('1600')
                    ->imageResizeTargetHeight('1600')
                    ->imageResizeUpscale(false), MobileImages::KEYS)),
        ];
    }

    /** @return list<Section|Text> */
    private function linkSections(): array
    {
        return [
            Text::make(__('admin_mobile_app.links.intro')),
            Section::make(__('admin_mobile_app.platforms.ios'))
                ->icon('heroicon-o-device-phone-mobile')
                ->schema([
                    TextInput::make('links.apple_team_id')
                        ->label(__('admin_mobile_app.fields.apple_team_id'))
                        ->helperText(__('admin_mobile_app.fields.apple_team_id_help'))
                        ->placeholder('ABCDE12345')
                        ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                        ->regex('/^\s*[A-Za-z0-9]{10}\s*$/'),
                    Text::make(fn () => __('admin_mobile_app.links.file', ['url' => route('app-links.apple')])),
                ]),
            Section::make(__('admin_mobile_app.platforms.android'))
                ->icon('heroicon-o-device-phone-mobile')
                ->schema([
                    Textarea::make('links.android_fingerprints')
                        ->label(__('admin_mobile_app.fields.android_fingerprints'))
                        ->helperText(__('admin_mobile_app.fields.android_fingerprints_help'))
                        ->placeholder('14:6D:E9:83:C5:73:06:50:D8:EE:B9:95:2F:34:FC:64:16:A0:83:42:E6:1D:BE:A8:8A:04:96:B2:3F:CF:44:E5')
                        ->rows(3)
                        ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                            foreach (preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) as $fingerprint) {
                                if (AppLinks::fingerprints($fingerprint) === []) {
                                    $fail(__('admin_mobile_app.validation.fingerprint', ['value' => $fingerprint]));

                                    return;
                                }
                            }
                        }),
                    Text::make(fn () => __('admin_mobile_app.links.file', ['url' => route('app-links.android')])),
                ]),
        ];
    }

    /** @return array<string, string> */
    public static function platformOptions(): array
    {
        return collect(AppClient::PLATFORMS)->mapWithKeys(fn (string $p) => [$p => __('admin_mobile_app.platforms.'.$p)])->all();
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $before = self::currentState();

        MobileFeatures::save((array) ($state['features'] ?? []));
        MobileUpdates::save((array) ($state['updates'] ?? []));
        MobileMaintenance::save((array) ($state['maintenance'] ?? []));
        MobilePosting::save((array) ($state['posting'] ?? []));
        MobileImages::save((array) ($state['images'] ?? []));
        AppLinks::save((array) ($state['links'] ?? []));

        $after = self::currentState();

        if ($after !== $before) {
            activity('mobile_app')
                ->causedBy(auth()->user())
                ->event('updated')
                ->withProperties(['old' => $before, 'attributes' => $after])
                ->log('Mobile app settings saved');
        }

        Notification::make()->title(__('admin_mobile_app.settings.saved'))->success()->send();
    }
}

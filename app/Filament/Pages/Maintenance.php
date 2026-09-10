<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class Maintenance extends Page implements HasForms
{
    use InteractsWithForms;
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.maintenance';

    public ?array $data = [];

    public function getTitle(): string
    {
        return __('maintenance.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('maintenance.navigation_label');
    }

    public function mount(): void
    {
        $data = $this->currentPayload();

        $this->form->fill([
            'secret' => $data['secret'] ?? Str::random(20),
            'retry' => $data['retry'] ?? null,
            'refresh' => $data['refresh'] ?? null,
            'allowed_ips' => implode("\n", (array) ($data['allowed'] ?? [])),
            'message_en' => $data['message']['en'] ?? __('maintenance.default_message_en'),
            'message_ar' => $data['message']['ar'] ?? __('maintenance.default_message_ar'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('maintenance.sections.message'))
                    ->description(__('maintenance.sections.message_desc'))
                    ->schema([
                        Grid::make(2)->schema([
                            Textarea::make('message_en')
                                ->label(__('maintenance.fields.message_en'))
                                ->rows(3)
                                ->maxLength(500)
                                ->required(),

                            Textarea::make('message_ar')
                                ->label(__('maintenance.fields.message_ar'))
                                ->rows(3)
                                ->maxLength(500)
                                ->required(),
                        ]),
                    ]),

                Section::make(__('maintenance.sections.access'))
                    ->description(__('maintenance.sections.access_desc'))
                    ->schema([
                        TextInput::make('secret')
                            ->label(__('maintenance.fields.secret'))
                            ->helperText(__('maintenance.fields.secret_helper'))
                            ->suffixAction(
                                Action::make('regenerate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->action(fn ($set) => $set('secret', Str::random(20)))
                            )
                            ->maxLength(64),

                        Textarea::make('allowed_ips')
                            ->label(__('maintenance.fields.allowed_ips'))
                            ->helperText(__('maintenance.fields.allowed_ips_helper'))
                            ->rows(2),
                    ]),

                Section::make(__('maintenance.sections.behavior'))
                    ->description(__('maintenance.sections.behavior_desc'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('retry')
                                ->label(__('maintenance.fields.retry'))
                                ->helperText(__('maintenance.fields.retry_helper'))
                                ->numeric()
                                ->minValue(1),

                            TextInput::make('refresh')
                                ->label(__('maintenance.fields.refresh'))
                                ->helperText(__('maintenance.fields.refresh_helper'))
                                ->numeric()
                                ->minValue(1),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function isDown(): bool
    {
        return app()->isDownForMaintenance();
    }

    public function downSince(): ?Carbon
    {
        $startedAt = $this->currentPayload()['started_at'] ?? null;

        return $startedAt ? Carbon::parse($startedAt) : null;
    }

    public function bypassUrl(): ?string
    {
        $secret = $this->currentPayload()['secret'] ?? null;

        return $secret ? rtrim(config('app.url'), '/').'/'.$secret : null;
    }

    public function enable(): void
    {
        $state = $this->form->getState();

        $allowed = collect(preg_split('/[\s,]+/', (string) ($state['allowed_ips'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn ($ip) => trim($ip))
            ->filter()
            ->values()
            ->all();

        $payload = [
            'except' => app(\App\Http\Middleware\PreventRequestsDuringMaintenance::class)->getExcludedPaths(),
            'redirect' => null,
            'retry' => filled($state['retry'] ?? null) ? (int) $state['retry'] : null,
            'refresh' => filled($state['refresh'] ?? null) ? (int) $state['refresh'] : null,
            'secret' => filled($state['secret'] ?? null) ? $state['secret'] : null,
            'status' => 503,
            'template' => null,
            'allowed' => $allowed,
            'message' => [
                'en' => $state['message_en'] ?? '',
                'ar' => $state['message_ar'] ?? '',
            ],
            'started_at' => $this->currentPayload()['started_at'] ?? now()->toIso8601String(),
        ];

        app(MaintenanceMode::class)->activate($payload);

        Notification::make()
            ->title(__('maintenance.notifications.enabled'))
            ->body($payload['secret'] ? __('maintenance.notifications.bypass_hint', ['url' => $this->bypassUrl()]) : null)
            ->warning()
            ->send();
    }

    public function disable(): void
    {
        app(MaintenanceMode::class)->deactivate();

        Notification::make()
            ->title(__('maintenance.notifications.disabled'))
            ->success()
            ->send();
    }

    protected function currentPayload(): array
    {
        if (! app()->isDownForMaintenance()) {
            return [];
        }

        try {
            return app(MaintenanceMode::class)->data();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * A grouped, at-a-glance snapshot of the environment this app is running
     * on — mirrors the "System Information" panel of a typical ops dashboard.
     *
     * @return array<string, array{label: string, items: array<int, array<string, mixed>>}>
     */
    public function systemInfoGroups(): array
    {
        $dbConnection = config('database.default');
        $environment = app()->environment();
        $debug = (bool) config('app.debug');
        $isDown = $this->isDown();
        $webServer = Str::before($_SERVER['SERVER_SOFTWARE'] ?? php_sapi_name(), '/');

        return [
            'application' => [
                'label' => __('maintenance.system.groups.application'),
                'items' => [
                    ['label' => __('maintenance.system.laravel'), 'value' => 'v'.app()->version()],
                    ['label' => __('maintenance.system.php'), 'value' => PHP_VERSION],
                    [
                        'label' => __('maintenance.system.environment'),
                        'badge' => ucfirst($environment),
                        'color' => match (true) {
                            $environment === 'production' => 'danger',
                            in_array($environment, ['staging', 'testing'], true) => 'warning',
                            default => 'success',
                        },
                    ],
                    ['label' => __('maintenance.system.timezone'), 'value' => config('app.timezone')],
                ],
            ],
            'status' => [
                'label' => __('maintenance.system.groups.status'),
                'items' => [
                    [
                        'label' => __('maintenance.system.debug_mode'),
                        'badge' => $debug ? __('maintenance.system.enabled') : __('maintenance.system.disabled'),
                        'color' => $debug ? 'danger' : 'success',
                    ],
                    [
                        'label' => __('maintenance.system.maintenance'),
                        'badge' => $isDown ? __('maintenance.system.active') : __('maintenance.system.inactive'),
                        'color' => $isDown ? 'danger' : 'success',
                    ],
                    ['label' => __('maintenance.system.queue'), 'value' => ucfirst((string) config('queue.default'))],
                    ['label' => __('maintenance.system.cache'), 'value' => ucfirst((string) config('cache.default'))],
                ],
            ],
            'infrastructure' => [
                'label' => __('maintenance.system.groups.infrastructure'),
                'items' => [
                    ['label' => __('maintenance.system.db_driver'), 'value' => ucfirst((string) $dbConnection)],
                    ['label' => __('maintenance.system.database'), 'value' => config("database.connections.{$dbConnection}.database", '—')],
                    ['label' => __('maintenance.system.session'), 'value' => ucfirst((string) config('session.driver'))],
                    ['label' => __('maintenance.system.mail'), 'value' => ucfirst((string) config('mail.default'))],
                ],
            ],
            'server' => [
                'label' => __('maintenance.system.groups.server'),
                'items' => [
                    ['label' => __('maintenance.system.os'), 'value' => PHP_OS],
                    ['label' => __('maintenance.system.php_memory'), 'value' => ini_get('memory_limit')],
                    ['label' => __('maintenance.system.extensions'), 'value' => __('maintenance.system.extensions_loaded', ['count' => count(get_loaded_extensions())])],
                    ['label' => __('maintenance.system.web_server'), 'value' => $webServer ?: 'CLI'],
                ],
            ],
        ];
    }

    /**
     * @return array{log: array{bytes: int, percent: float}, cache: array{bytes: int, percent: float}, disk: array{total: int, used: int, free: int, percent: float}}
     */
    public function usageStats(): array
    {
        $logBytes = $this->directorySize(storage_path('logs'));
        $cacheBytes = $this->directorySize(storage_path('framework/cache'));

        return [
            'log' => [
                'bytes' => $logBytes,
                'percent' => round(min(($logBytes / (10 * 1024 * 1024)) * 100, 100), 1),
            ],
            'cache' => [
                'bytes' => $cacheBytes,
                'percent' => round(min(($cacheBytes / (20 * 1024 * 1024)) * 100, 100), 1),
            ],
            'disk' => $this->diskUsage(),
        ];
    }

    public function storageLinked(): bool
    {
        return is_link(public_path('storage'));
    }

    /**
     * @return array{total: int, used: int, free: int, percent: float}
     */
    protected function diskUsage(): array
    {
        $path = storage_path();
        $total = (int) (@disk_total_space($path) ?: 0);
        $free = (int) (@disk_free_space($path) ?: 0);
        $used = max($total - $free, 0);

        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0.0,
        ];
    }

    protected function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }

        try {
            $size = 0;

            foreach (\Illuminate\Support\Facades\File::allFiles($path) as $file) {
                $size += $file->getSize();
            }

            return $size;
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }

    public function runArtisan(string $command): void
    {
        $allowed = [
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear',
            'optimize',
            'queue:restart',
            'storage:link',
        ];

        if (! in_array($command, $allowed, true)) {
            abort(403);
        }

        Artisan::call($command);

        Notification::make()
            ->title(__('maintenance.notifications.command_ran', ['command' => $command]))
            ->success()
            ->send();
    }
}

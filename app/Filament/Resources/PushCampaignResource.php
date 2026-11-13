<?php

namespace App\Filament\Resources;

use App\Filament\Pages\MobileApp\MobileAppSettings;
use App\Filament\Resources\PushCampaignResource\Pages\CreatePushCampaign;
use App\Filament\Resources\PushCampaignResource\Pages\EditPushCampaign;
use App\Filament\Resources\PushCampaignResource\Pages\ListPushCampaigns;
use App\Filament\Resources\PushCampaignResource\Pages\ViewPushCampaign;
use App\Filament\Resources\PushCampaignResource\Widgets;
use App\Jobs\SendPushCampaign;
use App\Models\Customer;
use App\Models\MobileAnnouncement;
use App\Models\MobileDevice;
use App\Models\PushCampaign;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Services\Push\ExpoPush;
use App\Support\MobileApp\AppVersion;
use App\Support\MobileApp\MobileAutoPush;
use App\Support\MobileApp\MobileScreens;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Throwable;

/** Push notification campaigns to the app's phones (App\Jobs\SendPushCampaign; the app's list: GET /api/v1/push/inbox). */
class PushCampaignResource extends Resource
{
    protected static ?string $model = PushCampaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'mobile-app/notifications';

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.mobile_app');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_mobile_app.campaigns.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_mobile_app.campaigns.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_mobile_app.campaigns.plural');
    }

    /** The wall clock admins type dates in (stored in UTC). */
    private static function timezone(): string
    {
        return (string) config('stable_bookings.timezone', 'Asia/Muscat');
    }

    private static function options(string $group, array $keys): array
    {
        return collect($keys)->mapWithKeys(fn (string $key) => [$key => __("admin_mobile_app.campaigns.{$group}.{$key}")])->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin_mobile_app.campaigns.sections.content'))
                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('en_title')->live(debounce: 500)->label(__('admin_mobile_app.fields.en_title'))->required()->maxLength(120),
                    TextInput::make('ar_title')->live(debounce: 500)->label(__('admin_mobile_app.fields.ar_title'))->required()->maxLength(120)
                        ->extraInputAttributes(['dir' => 'rtl']),
                    Textarea::make('en_body')->live(debounce: 500)->label(__('admin_mobile_app.fields.en_body'))->rows(3)->maxLength(500),
                    Textarea::make('ar_body')->live(debounce: 500)->label(__('admin_mobile_app.fields.ar_body'))->rows(3)->maxLength(500)
                        ->extraInputAttributes(['dir' => 'rtl']),
                    FileUpload::make('image_path')
                        ->label(__('admin_mobile_app.fields.image'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.image_help'))
                        ->image()
                        ->disk('public')
                        ->directory('mobile/campaigns')
                        ->maxSize(4096)
                        ->columnSpanFull(),
                ]),

            Section::make(__('admin_mobile_app.campaigns.sections.preview'))
                ->icon('heroicon-o-device-phone-mobile')
                ->description(__('admin_mobile_app.campaigns.fields.preview_help'))
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    View::make('filament.push-campaign.preview')
                        ->viewData(fn (Get $get) => [
                            'app' => SiteSetting::get('site_name_'.app()->getLocale()) ?: config('app.name'),
                            'messages' => [
                                'en' => ['title' => $get('en_title'), 'body' => $get('en_body')],
                                'ar' => ['title' => $get('ar_title') ?: $get('en_title'), 'body' => $get('ar_body') ?: $get('en_body')],
                            ],
                            'silent' => $get('sound') === false,
                        ]),
                ]),

            Section::make(__('admin_mobile_app.campaigns.sections.tap'))
                ->icon('heroicon-o-cursor-arrow-rays')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('action_type')
                        ->label(__('admin_mobile_app.campaigns.fields.action_type'))
                        ->options([
                            MobileAnnouncement::ACTION_NONE => __('admin_mobile_app.actions.none'),
                            MobileAnnouncement::ACTION_SCREEN => __('admin_mobile_app.actions.screen'),
                            MobileAnnouncement::ACTION_URL => __('admin_mobile_app.actions.url'),
                        ])
                        ->default(MobileAnnouncement::ACTION_NONE)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    Select::make('action_screen')
                        ->label(__('admin_mobile_app.fields.action_screen'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.action_screen_help'))
                        ->options(fn () => collect(MobileScreens::keys())->mapWithKeys(fn (string $key) => [$key => __('admin_mobile_app.screens.'.$key)])->all())
                        ->visible(fn (Get $get) => $get('action_type') === MobileAnnouncement::ACTION_SCREEN)
                        ->required(fn (Get $get) => $get('action_type') === MobileAnnouncement::ACTION_SCREEN),
                    TextInput::make('action_url')
                        ->label(__('admin_mobile_app.fields.action_url'))
                        ->url()
                        ->maxLength(2048)
                        ->visible(fn (Get $get) => $get('action_type') === MobileAnnouncement::ACTION_URL)
                        ->required(fn (Get $get) => $get('action_type') === MobileAnnouncement::ACTION_URL),
                ]),

            Section::make(__('admin_mobile_app.campaigns.sections.audience'))
                ->icon('heroicon-o-user-group')
                ->description(__('admin_mobile_app.campaigns.fields.cap_help', ['count' => PushCampaign::DAILY_CAP]))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('audience')
                        ->live()
                        ->label(__('admin_mobile_app.campaigns.fields.audience'))
                        ->options(self::options('audiences', PushCampaign::AUDIENCES))
                        ->default('everyone')
                        ->selectablePlaceholder(false)
                        ->required(),
                    Textarea::make('target_phones')
                        ->label(__('admin_mobile_app.campaigns.fields.target_phones'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.target_phones_help'))
                        ->placeholder("9222 3333\n96899887766")
                        ->rows(4)
                        ->live(onBlur: true)
                        // stored as a list; one number per line (commas and semicolons work too)
                        ->formatStateUsing(fn ($state) => is_array($state) ? implode("\n", $state) : $state)
                        ->dehydrateStateUsing(fn (?string $state) => self::phoneList($state) ?: null)
                        ->visible(fn (Get $get) => $get('audience') === 'phones')
                        ->required(fn (Get $get) => $get('audience') === 'phones'),
                    Select::make('target_stables')
                        ->label(__('admin_mobile_app.campaigns.fields.target_stables'))
                        ->helperText(fn (Get $get) => __('admin_mobile_app.campaigns.fields.target_stables_help.'.$get('audience')))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(fn () => Stable::query()->orderBy('en_name')->get()->mapWithKeys(fn (Stable $stable) => [$stable->getKey() => $stable->name])->all())
                        ->live()
                        ->visible(fn (Get $get) => in_array($get('audience'), ['stable_customers', 'stable_owners'], true))
                        ->required(fn (Get $get) => in_array($get('audience'), ['stable_customers', 'stable_owners'], true)),
                    Select::make('topic')
                        ->live()
                        ->label(__('admin_mobile_app.campaigns.fields.topic'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.topic_help'))
                        ->options(self::options('topics', MobileDevice::TOPICS))
                        ->default('news')
                        ->selectablePlaceholder(false)
                        ->required(),
                    CheckboxList::make('platforms')
                        ->live()
                        ->label(__('admin_mobile_app.fields.platforms'))
                        ->options(MobileAppSettings::platformOptions())
                        ->default(array_keys(MobileAppSettings::platformOptions()))
                        ->columns(2)
                        ->required(),
                    CheckboxList::make('locales')
                        ->live()
                        ->label(__('admin_mobile_app.fields.locales'))
                        ->helperText(__('admin_mobile_app.fields.locales_help'))
                        ->options(fn () => collect(config('languages.available', []))->map(fn (array $l, string $code) => $l['name'] ?? $code)->all())
                        ->columns(2),
                    TextInput::make('min_version')
                        ->live(onBlur: true)
                        ->label(__('admin_mobile_app.fields.announcement_min_version'))
                        ->regex(AppVersion::PATTERN)
                        ->maxLength(14),
                    TextInput::make('max_version')
                        ->live(onBlur: true)
                        ->label(__('admin_mobile_app.fields.announcement_max_version'))
                        ->regex(AppVersion::PATTERN)
                        ->maxLength(14)
                        ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            $min = (string) $get('min_version');

                            if (AppVersion::valid((string) $value) && AppVersion::valid($min) && AppVersion::lessThan((string) $value, $min)) {
                                $fail(__('admin_mobile_app.validation.max_below_min'));
                            }
                        }),
                    TextInput::make('active_days')
                        ->live(onBlur: true)
                        ->label(__('admin_mobile_app.campaigns.fields.active_days'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.active_days_help'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(365),
                    TextEntry::make('reach')
                        ->label(__('admin_widgets.push.reach'))
                        ->helperText(__('admin_widgets.push.reach_help'))
                        ->icon('heroicon-o-signal')
                        ->color('primary')
                        ->state(fn (Get $get) => trans_choice('admin_widgets.push.reach_phones', $count = self::reach($get), ['count' => number_format($count)]))
                        ->visible(fn (?PushCampaign $record) => $record?->editable() ?? true)
                        ->columnSpanFull(),
                ]),

            Section::make(__('admin_mobile_app.campaigns.sections.delivery'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->columns(2)
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    Select::make('priority')
                        ->label(__('admin_mobile_app.campaigns.fields.priority'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.priority_help'))
                        ->options(self::options('priorities', ['normal', 'high']))
                        ->default('normal')
                        ->selectablePlaceholder(false)
                        ->required(),
                    TextInput::make('ttl_hours')
                        ->label(__('admin_mobile_app.campaigns.fields.ttl_hours'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.ttl_hours_help'))
                        ->placeholder('24')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(672),
                    Toggle::make('sound')
                        ->label(__('admin_mobile_app.campaigns.fields.sound'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.sound_help'))
                        ->default(true)
                        ->live(),
                    Select::make('channel')
                        ->label(__('admin_mobile_app.campaigns.fields.channel'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.channel_help'))
                        ->options(self::options('channels', PushCampaign::CHANNELS))
                        ->default('default')
                        ->selectablePlaceholder(false)
                        ->required(),
                ]),

            Section::make(__('admin_mobile_app.campaigns.sections.sending'))
                ->icon('heroicon-o-paper-airplane')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    DateTimePicker::make('scheduled_at')
                        ->label(__('admin_mobile_app.campaigns.fields.scheduled_at'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.scheduled_at_help', ['zone' => self::timezone()]))
                        ->native(false)
                        ->seconds(false)
                        ->minDate(fn (?PushCampaign $record) => $record?->scheduled_at?->isPast() ? null : now()->startOfMinute()),
                    TextInput::make('spread_minutes')
                        ->label(__('admin_mobile_app.campaigns.fields.spread_minutes'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.spread_minutes_help'))
                        ->placeholder('30')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(240),
                    Toggle::make('quiet_hours')
                        ->label(__('admin_mobile_app.campaigns.fields.quiet_hours'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.quiet_hours_help', [
                            'from' => sprintf('%02d:00', PushCampaign::QUIET_FROM), 'until' => sprintf('%02d:00', PushCampaign::QUIET_UNTIL),
                        ])),
                    Toggle::make('ignore_cap')
                        ->label(__('admin_mobile_app.campaigns.fields.ignore_cap'))
                        ->helperText(__('admin_mobile_app.campaigns.fields.ignore_cap_help', ['count' => PushCampaign::DAILY_CAP]))
                        ->live(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(app()->getLocale() === 'ar' ? 'ar_title' : 'en_title')
                    ->label(__('admin_mobile_app.fields.title'))
                    ->searchable(['en_title', 'ar_title'])
                    ->wrap(),
                TextColumn::make('source')
                    ->label(__('admin_mobile_app.campaigns.fields.automatic'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => __('admin_mobile_app.features.'.MobileAutoPush::feature($state)))
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label(__('admin_mobile_app.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin_mobile_app.campaigns.status.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success',
                        'scheduled' => 'info',
                        'sending' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'sent' => 'heroicon-o-check-circle',
                        'scheduled' => 'heroicon-o-clock',
                        'sending' => 'heroicon-o-paper-airplane',
                        default => 'heroicon-o-pencil-square',
                    }),
                TextColumn::make('audience')
                    ->label(__('admin_mobile_app.campaigns.fields.audience'))
                    ->formatStateUsing(fn (string $state) => __('admin_mobile_app.campaigns.audiences.'.$state)),
                TextColumn::make('topic')
                    ->label(__('admin_mobile_app.campaigns.fields.topic'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin_mobile_app.campaigns.topics.'.$state)),
                TextColumn::make('when')
                    ->label(__('admin_mobile_app.campaigns.fields.scheduled_at'))
                    ->state(fn (PushCampaign $record) => $record->sent_at ?? $record->scheduled_at)
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('recipients')
                    ->label(__('admin_mobile_app.campaigns.fields.recipients'))
                    ->numeric(),
                TextColumn::make('accepted')
                    ->label(__('admin_mobile_app.campaigns.fields.accepted'))
                    ->numeric(),
                TextColumn::make('opened')
                    ->label(__('admin_mobile_app.campaigns.fields.opened'))
                    ->numeric(),
                TextColumn::make('open_rate')
                    ->label(__('admin_mobile_app.campaigns.fields.open_rate'))
                    ->state(fn (PushCampaign $record) => $record->accepted > 0 ? round(100 * $record->opened / $record->accepted, 1).'%' : null)
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->recordUrl(fn (PushCampaign $record) => $record->editable() && (auth()->user()?->can('update', $record) ?? false)
                ? self::getUrl('edit', ['record' => $record])
                : self::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin_mobile_app.fields.status'))
                    ->options(self::options('status', PushCampaign::STATUSES)),
                TernaryFilter::make('source')
                    ->label(__('admin_mobile_app.campaigns.fields.automatic'))
                    ->nullable(),
            ])
            ->recordActions([
                Action::make('send_now')
                    ->label(__('admin_mobile_app.campaigns.send_now'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription(__('admin_mobile_app.campaigns.send_now_confirm'))
                    ->visible(fn (PushCampaign $record) => $record->editable() && (auth()->user()?->can('update', $record) ?? false))
                    ->action(function (PushCampaign $record): void {
                        $record->forceFill(['scheduled_at' => now()])->save();
                        SendPushCampaign::dispatch($record->getKey());
                        Notification::make()->success()->title(__('admin_mobile_app.campaigns.send_now_done'))->send();
                    }),
                Action::make('report')
                    ->label(__('admin_widgets.push.report'))
                    ->icon('heroicon-o-chart-bar')
                    ->color('gray')
                    ->url(fn (PushCampaign $record) => self::getUrl('view', ['record' => $record]))
                    ->visible(fn (PushCampaign $record) => $record->status === 'sent'),
                ActionGroup::make([
                    Action::make('pause')
                        ->label(__('admin_widgets.push.pause'))
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription(__('admin_widgets.push.pause_confirm'))
                        ->visible(fn (PushCampaign $record) => $record->status === 'scheduled' && (auth()->user()?->can('update', $record) ?? false))
                        ->action(function (PushCampaign $record): void {
                            // claimed by SendPushCampaign meanwhile = already going out; leave it
                            $paused = PushCampaign::query()->whereKey($record->getKey())->where('status', 'scheduled')
                                ->update(['status' => 'draft', 'scheduled_at' => null]);
                            $paused
                                ? Notification::make()->success()->title(__('admin_widgets.push.paused'))->send()
                                : Notification::make()->warning()->title(__('admin_widgets.push.too_late'))->send();
                        }),
                    self::testAction(fn (PushCampaign $record) => $record)
                        ->visible(fn (PushCampaign $record) => auth()->user()?->can('update', $record) ?? false),
                    EditAction::make(),
                    ReplicateAction::make()
                        ->excludeAttributes(['source', 'source_id', 'status', 'scheduled_at', 'sent_at', 'recipients', 'accepted', 'opened'])
                        ->beforeReplicaSaved(fn (PushCampaign $replica) => $replica->forceFill(['status' => 'draft', 'created_by' => auth()->id()])),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** How many phones the campaign being edited would go to right now (audience, topic, filters, daily cap, the tapped screen's feature). */
    private static function reach(Get $get): int
    {
        return (new PushCampaign([
            'audience' => $get('audience') ?: 'everyone',
            'target_phones' => self::phoneList($get('target_phones')),
            'target_stables' => array_map('intval', (array) $get('target_stables')),
            'ignore_cap' => (bool) $get('ignore_cap'),
            'topic' => $get('topic') ?: 'news',
            'platforms' => $get('platforms') ?: null,
            'locales' => $get('locales') ?: null,
            'min_version' => AppVersion::valid((string) $get('min_version')) ? $get('min_version') : null,
            'max_version' => AppVersion::valid((string) $get('max_version')) ? $get('max_version') : null,
            'active_days' => is_numeric($get('active_days')) ? (int) $get('active_days') : null,
            'action_type' => $get('action_type'),
            'action_screen' => $get('action_screen'),
        ]))->devices()->count();
    }

    public static function getWidgets(): array
    {
        return [
            Widgets\PushStatsOverview::class,
            Widgets\PushActivityChart::class,
            Widgets\PushFunnel::class,
            Widgets\PushAudience::class,
            Widgets\PushTopics::class,
            Widgets\UpcomingPushCampaigns::class,
            Widgets\CampaignFunnel::class,
            Widgets\CampaignOpensChart::class,
        ];
    }

    /** One number per line (or separated by commas/semicolons), each spelled like PhoneNumber::normalize(). */
    public static function phoneList(mixed $state): array
    {
        $parts = is_array($state) ? $state : preg_split('/[\r\n,;]+/', (string) $state);

        return collect($parts)->map(fn ($p) => PhoneNumber::normalize(trim((string) $p)))->filter()->unique()->values()->all();
    }

    /**
     * "Send a test": the table row's campaign, or the create/edit form's current text on those pages.
     *
     * @param  \Closure(mixed ...): ?PushCampaign  $campaign  resolved like any Filament action closure
     */
    public static function testAction(\Closure $campaign): Action
    {
        return Action::make('send_test')
            ->label(__('admin_mobile_app.campaigns.send_test'))
            ->icon('heroicon-o-beaker')
            ->color('gray')
            ->schema([
                TextInput::make('test_phone')
                    ->label(__('admin_mobile_app.campaigns.fields.test_phone'))
                    ->helperText(__('admin_mobile_app.campaigns.fields.test_phone_help'))
                    ->placeholder('9222 3333')
                    ->tel()
                    ->required(),
            ])
            ->action(function (Action $action, array $data) use ($campaign): void {
                $record = $action->evaluate($campaign);

                if (! $record?->localized('title')) {
                    Notification::make()->warning()->title(__('admin_mobile_app.campaigns.test_needs_title'))->send();

                    return;
                }

                self::sendTest($record, (string) $data['test_phone']);
            });
    }

    /** The campaign to the phones signed in with this number (customer or stable owner), outside its counts. */
    private static function sendTest(PushCampaign $campaign, string $phone): void
    {
        $devices = MobileDevice::query()->signedInWithPhones([$phone])->get();

        try {
            $sent = $devices->isEmpty() ? [] : app(ExpoPush::class)->send($devices, fn () => array_filter([
                'title' => $campaign->localized('title'),
                'body' => $campaign->localized('body'),
            ]), $campaign->payload(), 'default', $campaign->pushOptions());
        } catch (Throwable $e) {
            report($e);
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        $sent === []
            ? Notification::make()->warning()->title(__('admin_mobile_app.campaigns.test_none'))->send()
            : Notification::make()->success()->title(__('admin_mobile_app.campaigns.test_sent', ['count' => count($sent)]))->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPushCampaigns::route('/'),
            'create' => CreatePushCampaign::route('/create'),
            'view' => ViewPushCampaign::route('/{record}'),
            'edit' => EditPushCampaign::route('/{record}/edit'),
        ];
    }
}

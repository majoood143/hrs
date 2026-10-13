<?php

namespace App\Filament\Resources;

use App\Filament\Pages\MobileApp\MobileAppSettings;
use App\Filament\Resources\MobileAnnouncementResource\Pages\CreateMobileAnnouncement;
use App\Filament\Resources\MobileAnnouncementResource\Pages\EditMobileAnnouncement;
use App\Filament\Resources\MobileAnnouncementResource\Pages\ListMobileAnnouncements;
use App\Models\MobileAnnouncement;
use App\Support\MobileApp\AppVersion;
use App\Support\MobileApp\MobileScreens;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** Popups shown in the mobile app (GET /api/v1/app-status → announcements). */
class MobileAnnouncementResource extends Resource
{
    protected static ?string $model = MobileAnnouncement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'mobile-app/announcements';

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.mobile_app');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_mobile_app.announcements.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_mobile_app.announcements.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_mobile_app.announcements.plural');
    }

    /** The wall clock admins type dates in (stored in UTC). */
    private static function timezone(): string
    {
        return (string) config('stable_bookings.timezone', 'Asia/Muscat');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin_mobile_app.announcements.sections.content'))
                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('en_title')->label(__('admin_mobile_app.fields.en_title'))->required()->maxLength(120),
                    TextInput::make('ar_title')->label(__('admin_mobile_app.fields.ar_title'))->required()->maxLength(120)
                        ->extraInputAttributes(['dir' => 'rtl']),
                    Textarea::make('en_body')->label(__('admin_mobile_app.fields.en_body'))->rows(4)->maxLength(1000),
                    Textarea::make('ar_body')->label(__('admin_mobile_app.fields.ar_body'))->rows(4)->maxLength(1000)
                        ->extraInputAttributes(['dir' => 'rtl']),
                    FileUpload::make('image_path')
                        ->label(__('admin_mobile_app.fields.image'))
                        ->helperText(__('admin_mobile_app.fields.image_help'))
                        ->image()
                        ->disk('public')
                        ->directory('mobile/announcements')
                        ->maxSize(4096)
                        ->columnSpanFull(),
                ]),

            Section::make(__('admin_mobile_app.announcements.sections.button'))
                ->icon('heroicon-o-cursor-arrow-rays')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('action_type')
                        ->label(__('admin_mobile_app.fields.action_type'))
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
                        ->helperText(__('admin_mobile_app.fields.action_screen_help'))
                        ->options(fn () => collect(MobileScreens::keys())->mapWithKeys(fn (string $key) => [$key => __('admin_mobile_app.screens.'.$key)])->all())
                        ->visible(fn (Get $get) => $get('action_type') === MobileAnnouncement::ACTION_SCREEN)
                        ->required(fn (Get $get) => $get('action_type') === MobileAnnouncement::ACTION_SCREEN),
                    TextInput::make('action_url')
                        ->label(__('admin_mobile_app.fields.action_url'))
                        ->url()
                        ->maxLength(2048)
                        ->visible(fn (Get $get) => $get('action_type') === MobileAnnouncement::ACTION_URL)
                        ->required(fn (Get $get) => $get('action_type') === MobileAnnouncement::ACTION_URL),
                    Grid::make(2)
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => in_array($get('action_type'), [MobileAnnouncement::ACTION_URL, MobileAnnouncement::ACTION_SCREEN], true))
                        ->schema([
                            TextInput::make('en_button_label')->label(__('admin_mobile_app.fields.en_button_label'))
                                ->placeholder(__('api.app.announcement_button', [], 'en'))->maxLength(40),
                            TextInput::make('ar_button_label')->label(__('admin_mobile_app.fields.ar_button_label'))
                                ->placeholder(__('api.app.announcement_button', [], 'ar'))->maxLength(40)
                                ->extraInputAttributes(['dir' => 'rtl']),
                        ]),
                ]),

            Section::make(__('admin_mobile_app.announcements.sections.audience'))
                ->icon('heroicon-o-user-group')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    CheckboxList::make('platforms')
                        ->label(__('admin_mobile_app.fields.platforms'))
                        ->options(MobileAppSettings::platformOptions())
                        ->default(array_keys(MobileAppSettings::platformOptions()))
                        ->columns(2)
                        ->required(),
                    CheckboxList::make('locales')
                        ->label(__('admin_mobile_app.fields.locales'))
                        ->helperText(__('admin_mobile_app.fields.locales_help'))
                        ->options(fn () => collect(config('languages.available', []))->map(fn (array $l, string $code) => $l['name'] ?? $code)->all())
                        ->columns(2),
                    TextInput::make('min_version')
                        ->label(__('admin_mobile_app.fields.announcement_min_version'))
                        ->placeholder('1.0.0')
                        ->regex(AppVersion::PATTERN)
                        ->maxLength(14),
                    TextInput::make('max_version')
                        ->label(__('admin_mobile_app.fields.announcement_max_version'))
                        ->placeholder('1.9.9')
                        ->regex(AppVersion::PATTERN)
                        ->maxLength(14)
                        ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            $min = (string) $get('min_version');

                            if (AppVersion::valid((string) $value) && AppVersion::valid($min) && AppVersion::lessThan((string) $value, $min)) {
                                $fail(__('admin_mobile_app.validation.max_below_min'));
                            }
                        }),
                ]),

            Section::make(__('admin_mobile_app.announcements.sections.display'))
                ->icon('heroicon-o-calendar-days')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    DateTimePicker::make('starts_at')
                        ->label(__('admin_mobile_app.fields.starts_at'))
                        ->helperText(__('admin_mobile_app.fields.dates_help', ['zone' => self::timezone()]))
                        ->timezone(self::timezone())
                        ->native(false)
                        ->seconds(false),
                    DateTimePicker::make('ends_at')
                        ->label(__('admin_mobile_app.fields.ends_at'))
                        ->timezone(self::timezone())
                        ->native(false)
                        ->seconds(false)
                        ->after('starts_at'),
                    Select::make('frequency')
                        ->label(__('admin_mobile_app.fields.frequency'))
                        ->options(collect(MobileAnnouncement::FREQUENCIES)->mapWithKeys(fn (string $f) => [$f => __('admin_mobile_app.frequency.'.$f)])->all())
                        ->default('once')
                        ->selectablePlaceholder(false)
                        ->required(),
                    TextInput::make('priority')
                        ->label(__('admin_mobile_app.fields.priority'))
                        ->helperText(__('admin_mobile_app.fields.priority_help'))
                        ->numeric()
                        ->integer()
                        ->minValue(-100)
                        ->maxValue(100)
                        ->default(0)
                        ->required(),
                    Toggle::make('dismissible')
                        ->label(__('admin_mobile_app.fields.dismissible'))
                        ->helperText(__('admin_mobile_app.fields.dismissible_help'))
                        ->default(true)
                        ->inline(false)
                        ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                            if (! $value && in_array($get('action_type'), [null, '', MobileAnnouncement::ACTION_NONE], true)) {
                                $fail(__('admin_mobile_app.validation.needs_button'));
                            }
                        }),
                    Toggle::make('is_active')
                        ->label(__('admin_mobile_app.fields.is_active'))
                        ->default(true)
                        ->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label(__('admin_mobile_app.fields.image'))
                    ->disk('public')
                    ->square(),
                TextColumn::make(app()->getLocale() === 'ar' ? 'ar_title' : 'en_title')
                    ->label(__('admin_mobile_app.fields.title'))
                    ->searchable(['en_title', 'ar_title'])
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('admin_mobile_app.fields.status'))
                    ->state(fn (MobileAnnouncement $record) => $record->status())
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin_mobile_app.status.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        'live' => 'success',
                        'scheduled' => 'info',
                        'ended' => 'gray',
                        default => 'warning',
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'live' => 'heroicon-o-signal',
                        'scheduled' => 'heroicon-o-clock',
                        'ended' => 'heroicon-o-check-circle',
                        default => 'heroicon-o-pause-circle',
                    }),
                TextColumn::make('platforms')
                    ->label(__('admin_mobile_app.fields.platforms'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin_mobile_app.platforms.'.$state)),
                TextColumn::make('frequency')
                    ->label(__('admin_mobile_app.fields.frequency'))
                    ->formatStateUsing(fn (string $state) => __('admin_mobile_app.frequency.'.$state)),
                TextColumn::make('priority')
                    ->label(__('admin_mobile_app.fields.priority'))
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label(__('admin_mobile_app.fields.starts_at'))
                    ->dateTime(timezone: self::timezone())
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('admin_mobile_app.fields.ends_at'))
                    ->dateTime(timezone: self::timezone())
                    ->placeholder('—')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('admin_mobile_app.fields.is_active')),
            ])
            ->defaultSort('priority', 'desc')
            ->filters([
                TernaryFilter::make('is_active')->label(__('admin_mobile_app.fields.is_active')),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    ReplicateAction::make()
                        ->beforeReplicaSaved(fn (MobileAnnouncement $replica) => $replica->is_active = false),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMobileAnnouncements::route('/'),
            'create' => CreateMobileAnnouncement::route('/create'),
            'edit' => EditMobileAnnouncement::route('/{record}/edit'),
        ];
    }
}

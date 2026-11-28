<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GameCampaignResource\Pages\CreateGameCampaign;
use App\Filament\Resources\GameCampaignResource\Pages\EditGameCampaign;
use App\Filament\Resources\GameCampaignResource\Pages\ListGameCampaigns;
use App\Filament\Resources\GameCampaignResource\RelationManagers\AttemptsRelationManager;
use App\Filament\Resources\GameCampaignResource\RelationManagers\AwardsRelationManager;
use App\Filament\Resources\GameCampaignResource\RelationManagers\RacesRelationManager;
use App\Models\GameCampaign;
use App\Models\PromoCode;
use App\Models\SiteSetting;
use App\Support\Games\RaceCatalog;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** Admin → Games: campaigns of the horse racing game (dates, race, limits, leaderboard, prizes); races and prizes given are tabs. */
class GameCampaignResource extends Resource
{
    protected static ?string $model = GameCampaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('games.admin.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('games.admin.campaign');
    }

    public static function getPluralModelLabel(): string
    {
        return __('games.admin.campaigns');
    }

    public static function form(Schema $schema): Schema
    {
        $rtl = ['dir' => 'rtl'];
        $f = fn (string $key) => __('games.admin.fields.'.$key);
        $p = fn (string $key) => __('games.admin.placeholders.'.$key);

        return $schema->components([
            Section::make(__('games.admin.sections.details'))
                ->icon('heroicon-o-information-circle')
                ->description(__('games.admin.help.show_on_site'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('en_name')->label($f('en_name'))->placeholder($p('en_name'))->required()->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, ?GameCampaign $record) => $record ? null : $set('slug', Str::slug((string) $state))),
                    TextInput::make('ar_name')->label($f('ar_name'))->placeholder($p('ar_name'))->required()->maxLength(255)->extraInputAttributes($rtl),
                    TextInput::make('slug')->label($f('slug'))->placeholder($p('slug'))->required()->alphaDash()->maxLength(120)->unique(ignoreRecord: true),
                    Select::make('status')->label($f('status'))->options(fn () => GameCampaign::statusOptions())->default('draft')->required()->selectablePlaceholder(false),
                    DateTimePicker::make('starts_at')->label($f('starts_at'))->placeholder($p('date'))->native(false)->seconds(false)->required(),
                    DateTimePicker::make('ends_at')->label($f('ends_at'))->placeholder($p('date'))->native(false)->seconds(false)->required()->after('starts_at'),
                    Textarea::make('en_description')->label($f('en_description'))->placeholder($p('en_description'))->rows(3),
                    Textarea::make('ar_description')->label($f('ar_description'))->placeholder($p('ar_description'))->rows(3)->extraInputAttributes($rtl),
                    Textarea::make('en_terms')->label($f('en_terms'))->placeholder($p('en_terms'))->rows(3),
                    Textarea::make('ar_terms')->label($f('ar_terms'))->placeholder($p('ar_terms'))->rows(3)->extraInputAttributes($rtl),
                ]),
            Section::make(__('games.admin.sections.race'))
                ->icon('heroicon-o-flag')
                ->description(__('games.admin.help.race'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('race_id')->label($f('race_id'))->helperText(__('games.admin.help.race_id'))->options(fn () => RaceCatalog::options())->default('rahba-sprint')->required()->selectablePlaceholder(false),
                    Select::make('difficulty')->label($f('difficulty'))->options(fn () => self::difficultyOptions())->default('normal')->required()->selectablePlaceholder(false),
                    TextInput::make('risk_threshold')->label($f('risk_threshold'))->helperText(__('games.admin.help.risk_threshold'))->placeholder('60')->integer()->minValue(1)->maxValue(100)->default(60)->required(),
                    TextInput::make('attempts_per_day')->label($f('attempts_per_day'))->helperText(__('games.admin.help.attempts_per_day'))->placeholder($p('attempts_per_day'))->integer()->minValue(1)->maxValue(100)->default(3),
                    TextInput::make('attempts_total')->label($f('attempts_total'))->helperText(__('games.admin.help.attempts_total'))->placeholder($p('attempts_total'))->integer()->minValue(1)->maxValue(1000),
                ]),
            Section::make(__('games.admin.sections.leaderboard'))
                ->icon('heroicon-o-chart-bar')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('leaderboard_public')->label($f('leaderboard_public'))->default(true)->inline(false),
                    TextInput::make('leaderboard_size')->label($f('leaderboard_size'))->placeholder($p('leaderboard_size'))->integer()->minValue(1)->maxValue(100)->default(10)->required(),
                    DateTimePicker::make('leaderboard_freeze_at')->label($f('leaderboard_freeze_at'))->helperText(__('games.admin.help.leaderboard_freeze_at'))->placeholder($p('date'))->native(false)->seconds(false),
                ]),
            Section::make(__('games.admin.sections.prizes'))
                ->icon('heroicon-o-gift')
                ->description(__('games.admin.help.prizes'))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Textarea::make('en_prize_text')->label($f('en_prize_text'))->placeholder($p('en_prize_text'))->rows(2),
                    Textarea::make('ar_prize_text')->label($f('ar_prize_text'))->placeholder($p('ar_prize_text'))->rows(2)->extraInputAttributes($rtl),
                    Repeater::make('prizes')
                        ->relationship()
                        ->hiddenLabel()
                        ->orderColumn(false)
                        ->schema([
                            TextInput::make('rank')->label($f('rank'))->placeholder('1')->integer()->minValue(1)->maxValue(100)->required()->distinct(),
                            TextInput::make('en_title')->label($f('en_title'))->placeholder($p('en_title'))->required()->maxLength(255),
                            TextInput::make('ar_title')->label($f('ar_title'))->placeholder($p('ar_title'))->required()->maxLength(255)->extraInputAttributes($rtl),
                            // a discount makes the winner a single-use promo code bound to their mobile
                            Select::make('discount_type')->label($f('discount_type'))->helperText(__('games.admin.help.discount'))->native(false)->live()
                                ->options(['percentage' => __('promo_codes.admin.types.percentage'), 'fixed' => __('promo_codes.admin.types.fixed')])
                                ->visible(fn () => PromoCode::enabled()),
                            TextInput::make('discount_value')->label($f('discount_value'))->numeric()->minValue(0.001)->step(0.001)
                                ->required(fn (Get $get) => filled($get('discount_type')))
                                ->maxValue(fn (Get $get) => $get('discount_type') === PromoCode::PERCENTAGE ? 100 : null)
                                ->suffix(fn (Get $get) => $get('discount_type') === PromoCode::PERCENTAGE ? '%' : SiteSetting::currency()['code'])
                                ->visible(fn (Get $get) => PromoCode::enabled() && filled($get('discount_type'))),
                            TextInput::make('max_discount_amount')->label(__('promo_codes.admin.fields.max_discount_amount'))->numeric()->minValue(0.001)->step(0.001)
                                ->suffix(SiteSetting::currency()['code'])
                                ->visible(fn (Get $get) => PromoCode::enabled() && $get('discount_type') === PromoCode::PERCENTAGE),
                            TextInput::make('valid_days')->label($f('valid_days'))->integer()->minValue(1)->maxValue(365)
                                ->visible(fn (Get $get) => PromoCode::enabled() && filled($get('discount_type'))),
                        ])
                        ->columns(3)
                        ->addActionLabel(__('games.admin.add_prize'))
                        ->disabled(fn (?GameCampaign $record) => (bool) $record?->isAwarded())
                        ->columnSpanFull(),
                ]),
            Section::make(__('games.admin.sections.branding'))
                ->icon('heroicon-o-photo')
                ->columns(2)
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    self::picture('cover_image', $f('cover_image')),
                    self::picture('sponsor_logo', $f('sponsor_logo')),
                    TextInput::make('en_sponsor')->label($f('en_sponsor'))->placeholder($p('en_sponsor'))->maxLength(255),
                    TextInput::make('ar_sponsor')->label($f('ar_sponsor'))->placeholder($p('ar_sponsor'))->maxLength(255)->extraInputAttributes($rtl),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make(app()->getLocale() === 'ar' ? 'ar_name' : 'en_name')->label(__('games.admin.fields.name'))->searchable(['en_name', 'ar_name'])->sortable(),
                TextColumn::make('status')->label(__('games.admin.fields.status'))->badge()
                    ->formatStateUsing(fn (string $state) => GameCampaign::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state) => ['published' => 'success', 'archived' => 'gray'][$state] ?? 'warning'),
                TextColumn::make('race_id')->label(__('games.admin.fields.race_id'))->formatStateUsing(fn (string $state) => RaceCatalog::options()[$state] ?? $state)->toggleable(),
                TextColumn::make('starts_at')->label(__('games.admin.fields.starts_at'))->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('ends_at')->label(__('games.admin.fields.ends_at'))->dateTime('j M Y, H:i')->sortable(),
                TextColumn::make('attempts_count')->label(__('games.admin.attempts'))->counts('attempts')->icon('heroicon-o-flag'),
                TextColumn::make('awarded_at')->label(__('games.admin.fields.awarded_at'))->dateTime('j M Y')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('games.admin.fields.status'))->options(fn () => GameCampaign::statusOptions()),
            ])
            ->recordActions([
                Action::make('game')->label(__('games.admin.open_game'))->icon('heroicon-o-play')
                    ->url(fn (GameCampaign $record) => $record->gameUrl())->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }

    /** @return array<string, string> */
    public static function difficultyOptions(): array
    {
        return array_combine(RaceCatalog::DIFFICULTIES, array_map(fn ($d) => __('games.admin.difficulties.'.$d), RaceCatalog::DIFFICULTIES));
    }

    private static function picture(string $name, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->helperText(__('games.admin.upload_hint'))
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(4096)
            ->disk('public')
            ->directory('games');
    }

    public static function getRelations(): array
    {
        return [RacesRelationManager::class, AttemptsRelationManager::class, AwardsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGameCampaigns::route('/'),
            'create' => CreateGameCampaign::route('/create'),
            'edit' => EditGameCampaign::route('/{record}/edit'),
        ];
    }
}

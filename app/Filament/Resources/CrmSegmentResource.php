<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmSegmentResource\Pages\CreateCrmSegment;
use App\Filament\Resources\CrmSegmentResource\Pages\EditCrmSegment;
use App\Filament\Resources\CrmSegmentResource\Pages\ListCrmSegments;
use App\Models\AppEvent;
use App\Models\CrmSegment;
use App\Models\Customer;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Saved groups of customers by what they did in the app and who they are; a push campaign audience. */
class CrmSegmentResource extends Resource
{
    protected static ?string $model = CrmSegment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-funnel';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('admin_navigation.crm');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_crm.segments.plural');
    }

    public static function getModelLabel(): string
    {
        return __('admin_crm.segments.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_crm.segments.plural');
    }

    /** @return array<string, string> every event name, in the admin's language */
    public static function eventOptions(): array
    {
        return collect([...AppEvent::CLIENT, ...AppEvent::SERVER])
            ->mapWithKeys(fn (string $name) => [$name => __('admin_crm.events.'.$name)])
            ->sort()
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        $number = fn (string $name, int $min, int $max) => TextInput::make($name)->integer()->minValue($min)->maxValue($max)->live(onBlur: true);

        return $schema->components([
            Section::make(__('admin_crm.segments.sections.segment'))
                ->icon('heroicon-o-funnel')
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->label(__('admin_crm.segments.fields.name'))->required()->maxLength(120),
                    Textarea::make('description')->label(__('admin_crm.segments.fields.description'))->rows(2)->maxLength(500),
                    // how many it holds now, as the rules change
                    Text::make(fn (Get $get) => __('admin_crm.segments.count', ['count' => CrmSegment::queryFor((array) $get('rules'))->count()])),
                ]),
            Section::make(__('admin_crm.segments.sections.activity'))
                ->icon('heroicon-o-cursor-arrow-rays')
                ->description(__('admin_crm.segments.activity_help'))
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('rules.did')->label(__('admin_crm.segments.fields.did'))->placeholder(__('admin_crm.segments.any_action'))->options(fn () => self::eventOptions())->searchable()->live(),
                    $number('rules.min_count', 1, 1000)->label(__('admin_crm.segments.fields.min_count'))->default(1)->visible(fn (Get $get) => filled($get('rules.did'))),
                    $number('rules.within_days', 1, 365)->label(__('admin_crm.segments.fields.within_days'))->default(30)->visible(fn (Get $get) => filled($get('rules.did'))),
                    Select::make('rules.did_not')->label(__('admin_crm.segments.fields.did_not'))->placeholder(__('admin_crm.segments.any_action'))->options(fn () => self::eventOptions())->searchable()->live(),
                    $number('rules.not_within_days', 1, 365)->label(__('admin_crm.segments.fields.not_within_days'))->default(30)->visible(fn (Get $get) => filled($get('rules.did_not'))),
                ]),
            Section::make(__('admin_crm.segments.sections.profile'))
                ->icon('heroicon-o-identification')
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('rules.gender')->label(__('admin_crm.fields.gender'))->placeholder(__('admin_crm.segments.anyone'))->options(self::genderOptions())->live(),
                    Select::make('rules.countries')->label(__('admin_crm.fields.country'))->placeholder(__('admin_crm.segments.anyone'))->multiple()->options(fn () => self::countryOptions())->live(),
                    Select::make('rules.source')->label(__('admin_crm.fields.source'))->placeholder(__('admin_crm.segments.anyone'))->options(self::sourceOptions())->live(),
                    $number('rules.age_min', 0, 120)->label(__('admin_crm.segments.fields.age_min')),
                    $number('rules.age_max', 0, 120)->label(__('admin_crm.segments.fields.age_max'))->gte('rules.age_min'),
                    $number('rules.min_completion', 0, 100)->label(__('admin_crm.segments.fields.min_completion'))->suffix('%'),
                    Toggle::make('rules.marketing_only')
                        ->label(__('admin_crm.segments.fields.marketing_only'))
                        ->helperText(__('admin_crm.segments.fields.marketing_only_help'))
                        ->live(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin_crm.segments.fields.name'))->searchable()->weight('medium')
                    ->description(fn (CrmSegment $record) => $record->description),
                TextColumn::make('customers')
                    ->label(__('admin_crm.segments.customers'))
                    ->getStateUsing(fn (CrmSegment $record) => $record->customers()->count()),
                TextColumn::make('campaigns_count')->label(__('admin_crm.segments.campaigns'))->counts('campaigns'),
                TextColumn::make('updated_at')->label(__('admin_crm.fields.updated_at'))->dateTime('M d, Y H:i')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    /** @return array<string, string> */
    public static function genderOptions(): array
    {
        return collect(Customer::GENDERS)->mapWithKeys(fn (string $g) => [$g => __('admin_crm.genders.'.$g)])->all();
    }

    /** @return array<string, string> */
    public static function sourceOptions(): array
    {
        return collect(CrmSegment::SOURCES)->mapWithKeys(fn (string $s) => [$s => __('admin_crm.sources.'.$s)])->all();
    }

    /** @return array<string, string> the countries customers have picked (ISO codes) */
    public static function countryOptions(): array
    {
        return Customer::query()->whereNotNull('country')->distinct()->orderBy('country')->pluck('country', 'country')->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCrmSegments::route('/'),
            'create' => CreateCrmSegment::route('/create'),
            'edit' => EditCrmSegment::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Stable\Resources\StableSchedules;

use App\Filament\Stable\Concerns\InteractsWithStable;
use App\Filament\Stable\Resources\StableSchedules\Pages\CreateStableSchedule;
use App\Filament\Stable\Resources\StableSchedules\Pages\EditStableSchedule;
use App\Filament\Stable\Resources\StableSchedules\Pages\ListStableSchedules;
use App\Models\BookingSlot;
use App\Models\StableOffering;
use App\Models\StableSchedule;
use App\Models\StableTrainer;
use App\Services\Stables\SlotSyncResult;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Weekly patterns ("Sun, Tue, Thu at 08:00 and 16:00") that the slot generator turns into dated
 * slots up to the stable's booking window.
 */
class StableScheduleResource extends Resource
{
    use InteractsWithStable;

    protected static ?string $model = StableSchedule::class;

    protected static ?string $tenantRelationshipName = 'schedules';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.setup');
    }

    public static function getModelLabel(): string
    {
        return __('stable_panel.schedules.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stable_panel.schedules.plural');
    }

    /** @return array<int, string> Carbon day number => name, in the week order used in Oman (Sunday first) */
    public static function weekdayOptions(bool $short = false): array
    {
        return collect([0, 1, 2, 3, 4, 5, 6])
            ->mapWithKeys(fn (int $day) => [$day => __('stable_panel.weekdays'.($short ? '_short' : '').'.'.$day)])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('stable_panel.schedules.sections.when'))
                    ->icon('heroicon-o-calendar-days')
                    ->columns(2)
                    ->schema([
                        Select::make('stable_offering_id')
                            ->label(__('stable_panel.fields.offering'))
                            ->relationship('offering', 'en_name')
                            ->getOptionLabelFromRecordUsing(fn (StableOffering $record) => $record->name)
                            ->required()
                            ->preload()
                            ->native(false)
                            ->columnSpanFull(),
                        CheckboxList::make('weekdays')
                            ->label(__('stable_panel.fields.weekdays'))
                            ->options(static::weekdayOptions())
                            ->columns(4)
                            ->bulkToggleable()
                            ->required()
                            ->columnSpanFull(),
                        Repeater::make('start_times')
                            ->label(__('stable_panel.fields.start_times'))
                            ->helperText(__('stable_panel.schedules.start_times_hint'))
                            ->simple(
                                TimePicker::make('time')
                                    ->seconds(false)
                                    ->required(),
                            )
                            ->minItems(1)
                            ->maxItems(24)
                            ->defaultItems(1)
                            ->addActionLabel(__('stable_panel.schedules.add_time'))
                            ->reorderable(false)
                            ->grid(3)
                            ->columnSpanFull(),
                    ]),
                Section::make(__('stable_panel.schedules.sections.details'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('valid_from')
                            ->label(__('stable_panel.fields.valid_from'))
                            ->default(fn () => BookingSlot::today())
                            ->required(),
                        DatePicker::make('valid_until')
                            ->label(__('stable_panel.fields.valid_until'))
                            ->helperText(__('stable_panel.schedules.valid_until_hint'))
                            ->afterOrEqual('valid_from'),
                        TextInput::make('capacity')
                            ->label(__('stable_panel.fields.capacity'))
                            ->helperText(__('stable_panel.schedules.capacity_hint'))
                            ->integer()
                            ->minValue(1)
                            ->maxValue(200),
                        Select::make('trainer_id')
                            ->label(__('stable_panel.fields.trainer'))
                            ->relationship('trainer', 'en_name', fn ($query) => $query->where('is_active', true))
                            ->getOptionLabelFromRecordUsing(fn (StableTrainer $record) => $record->name)
                            ->preload()
                            ->visible(fn () => static::staffEnabled()),
                        Toggle::make('is_active')
                            ->label(__('stable_panel.fields.is_active'))
                            ->helperText(__('stable_panel.schedules.active_hint'))
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['offering', 'trainer'])->withCount(['slots as upcoming_slots_count' => fn ($q) => $q->where('date', '>=', BookingSlot::today())]))
            ->columns([
                TextColumn::make('offering.en_name')
                    ->label(__('stable_panel.fields.offering'))
                    ->formatStateUsing(fn (StableSchedule $record) => $record->offering?->name),
                TextColumn::make('weekdays')
                    ->label(__('stable_panel.fields.weekdays'))
                    ->state(fn (StableSchedule $record) => collect($record->weekdayNumbers())->map(fn (int $day) => static::weekdayOptions(short: true)[$day])->all())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('start_times')
                    ->label(__('stable_panel.fields.start_times'))
                    ->state(fn (StableSchedule $record) => collect($record->startTimes())->map(fn (string $time) => substr($time, 0, 5))->all())
                    ->badge()
                    ->icon('heroicon-o-clock'),
                TextColumn::make('valid_from')
                    ->label(__('stable_panel.fields.valid'))
                    ->formatStateUsing(fn (StableSchedule $record) => $record->valid_from?->format('Y-m-d').' → '.($record->valid_until?->format('Y-m-d') ?? __('stable_panel.schedules.open_ended'))),
                TextColumn::make('upcoming_slots_count')
                    ->label(__('stable_panel.schedules.upcoming_slots'))
                    ->icon('heroicon-o-calendar'),
                TextColumn::make('trainer.en_name')
                    ->label(__('stable_panel.fields.trainer'))
                    ->formatStateUsing(fn (StableSchedule $record) => $record->trainer?->name)
                    ->visible(fn () => static::staffEnabled()),
                IconColumn::make('is_active')
                    ->label(__('stable_panel.fields.is_active'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(__('stable_panel.schedules.delete_hint')),
            ])
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateHeading(__('stable_panel.schedules.empty_heading'))
            ->emptyStateDescription(__('stable_panel.schedules.empty_description'));
    }

    public static function notifySync(SlotSyncResult $result): void
    {
        Notification::make()
            ->success()
            ->title(__('stable_panel.schedules.synced'))
            ->body(__('stable_panel.schedules.synced_body', [
                'created' => $result->created,
                'updated' => $result->updated,
                'removed' => $result->removed,
            ]).($result->kept > 0 ? ' '.__('stable_panel.schedules.synced_kept', ['kept' => $result->kept]) : ''))
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStableSchedules::route('/'),
            'create' => CreateStableSchedule::route('/create'),
            'edit' => EditStableSchedule::route('/{record}/edit'),
        ];
    }
}

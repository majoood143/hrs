<?php

namespace App\Filament\Stable\Resources\StableClosures;

use App\Filament\Stable\Resources\StableClosures\Pages\ManageStableClosures;
use App\Models\BookingSlot;
use App\Models\Stable;
use App\Models\StableClosure;
use App\Models\StableOffering;
use App\Services\Stables\SlotGenerator;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Days off (holidays, maintenance, weather): no slots are made on them, and open ones are taken down. */
class StableClosureResource extends Resource
{
    protected static ?string $model = StableClosure::class;

    protected static ?string $tenantRelationshipName = 'closures';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-no-symbol';

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.setup');
    }

    public static function getModelLabel(): string
    {
        return __('stable_panel.closures.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('stable_panel.closures.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                DatePicker::make('starts_on')
                    ->label(__('stable_panel.fields.starts_on'))
                    ->default(fn () => BookingSlot::today())
                    ->required(),
                DatePicker::make('ends_on')
                    ->label(__('stable_panel.fields.ends_on'))
                    ->afterOrEqual('starts_on')
                    ->required(),
                Select::make('stable_offering_id')
                    ->label(__('stable_panel.fields.offering'))
                    ->placeholder(__('stable_panel.closures.whole_stable'))
                    ->helperText(__('stable_panel.closures.offering_hint'))
                    ->relationship('offering', 'en_name')
                    ->getOptionLabelFromRecordUsing(fn (StableOffering $record) => $record->name)
                    ->preload(),
                TextInput::make('reason')
                    ->label(__('stable_panel.fields.reason'))
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('offering'))
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('starts_on')
                    ->label(__('stable_panel.closures.period'))
                    ->formatStateUsing(fn (StableClosure $record) => $record->starts_on->format('Y-m-d').($record->ends_on->equalTo($record->starts_on) ? '' : ' → '.$record->ends_on->format('Y-m-d')))
                    ->icon('heroicon-o-calendar')
                    ->sortable(),
                TextColumn::make('offering.en_name')
                    ->label(__('stable_panel.fields.offering'))
                    ->formatStateUsing(fn (StableClosure $record) => $record->offering?->name)
                    ->placeholder(__('stable_panel.closures.whole_stable')),
                TextColumn::make('reason')
                    ->label(__('stable_panel.fields.reason'))
                    ->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make()->after(fn (StableClosure $record) => static::apply($record)),
                DeleteAction::make()->after(fn (StableClosure $record) => static::reopen($record)),
            ])
            ->emptyStateIcon('heroicon-o-no-symbol')
            ->emptyStateHeading(__('stable_panel.closures.empty_heading'));
    }

    /** Slots for the days no longer closed come back, and the closed days lose theirs. */
    public static function apply(StableClosure $closure): void
    {
        $generator = app(SlotGenerator::class);
        $generator->syncStable($closure->stable);
        $result = $generator->applyClosure($closure);

        Notification::make()
            ->success()
            ->title(__('stable_panel.closures.applied', ['removed' => $result->removed]))
            ->body($result->kept > 0 ? __('stable_panel.closures.kept', ['kept' => $result->kept]) : null)
            ->send();
    }

    public static function reopen(StableClosure $closure): void
    {
        $stable = Stable::query()->find($closure->stable_id);

        if ($stable) {
            app(SlotGenerator::class)->syncStable($stable);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStableClosures::route('/'),
        ];
    }
}

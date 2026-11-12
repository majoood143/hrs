<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JockeyResource\Pages\CreateJockey;
use App\Filament\Resources\JockeyResource\Pages\EditJockey;
use App\Filament\Resources\JockeyResource\Pages\ListJockeys;
use App\Filament\Resources\JockeyResource\Pages\ViewJockey;
use App\Filament\Support\LocationFields;
use App\Filament\Support\PendingPosts;
use App\Filament\Support\PlaceFormFields;
use App\Models\Jockey;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class JockeyResource extends Resource
{
    protected static ?string $model = Jockey::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.directory');
    }

    public static function getModelLabel(): string
    {
        return __('jockeys.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('jockeys.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('jockeys.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return PendingPosts::navigationBadge(static::$model);
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return PendingPosts::navigationBadgeColor(static::$model);
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return PendingPosts::navigationBadgeTooltip(static::$model);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Jockey')
                    ->tabs([
                        Tab::make(__('jockeys.tabs.info'))
                            ->schema([
                                TextInput::make('en_name')
                                    ->label(__('jockeys.name_en'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('ar_name')
                                    ->label(__('jockeys.name_ar'))
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                                CheckboxList::make('types')
                                    ->label(__('jockeys.types_label'))
                                    ->options(fn () => Jockey::typeOptions())
                                    ->columns(3)
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('years_experience')
                                    ->label(__('jockeys.years_experience'))
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(80),
                            ])->icon('heroicon-o-information-circle')
                            ->columns(2),
                        Tab::make(__('jockeys.tabs.location'))
                            ->schema([
                                ...LocationFields::make(fn (string $field) => __("jockeys.{$field}")),
                            ])->icon('heroicon-o-map-pin')
                            ->columns(3),
                        Tab::make(__('jockeys.tabs.listing'))
                            ->schema([
                                Select::make('status')
                                    ->label(__('jockeys.status'))
                                    ->options(fn () => Jockey::statusOptions())
                                    ->default('active')
                                    ->required(),
                                TextInput::make('contact_number')
                                    ->label(__('jockeys.contact_number'))
                                    ->tel()
                                    ->maxLength(32)
                                    ->required(),
                                PlaceFormFields::coverPhoto('jockeys/covers', __('jockeys.cover_photo'))
                                    ->columnSpanFull(),
                                Textarea::make('description_en')
                                    ->label(__('jockeys.description_en'))
                                    ->rows(3),
                                Textarea::make('description_ar')
                                    ->label(__('jockeys.description_ar'))
                                    ->rows(3)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                            ])->icon('heroicon-o-identification')
                            ->columns(2),
                        Tab::make(__('jockeys.tabs.racing'))
                            ->schema([
                                TextInput::make('racing_jockey_id')
                                    ->label(__('jockeys.racing_jockey_id'))
                                    ->helperText(__('jockeys.racing_jockey_id_help'))
                                    ->integer()
                                    ->minValue(1),
                                Toggle::make('racing_verified')
                                    ->label(__('jockeys.racing_verified'))
                                    ->helperText(__('jockeys.racing_verified_help')),
                            ])->icon('heroicon-o-flag')
                            ->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $name = LocationFields::nameColumn();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('country'))
            ->columns([
                ImageColumn::make('cover_photo')
                    ->label(__('jockeys.cover_photo'))
                    ->disk('public')
                    ->circular(),
                TextColumn::make($name)
                    ->label(__('jockeys.name'))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable(),
                TextColumn::make('types')
                    ->label(__('jockeys.types_label'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Jockey::typeOptions()[$state] ?? $state),
                TextColumn::make('years_experience')
                    ->label(__('jockeys.years_experience'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('contact_number')
                    ->label(__('jockeys.contact_number'))
                    ->searchable(),
                TextColumn::make('city.'.$name)
                    ->label(__('jockeys.city'))
                    ->description(fn (Jockey $record): string => (string) $record->country?->{$name})
                    ->searchable(['en_name', 'ar_name']),
                IconColumn::make('racing_verified')
                    ->label(__('jockeys.tabs.racing'))
                    ->boolean(),
                PendingPosts::activeToggle(Jockey::statusOptions()['active']),
                TextColumn::make('created_at')
                    ->label(__('jockeys.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('jockeys.status'))
                    ->options(fn () => Jockey::statusOptions()),
                Filter::make('type')
                    ->schema([
                        Select::make('type')
                            ->label(__('jockeys.types_label'))
                            ->options(fn () => Jockey::typeOptions()),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when($data['type'] ?? null, fn ($q, $type) => $q->whereJsonContains('types', $type))),
                SelectFilter::make('city_id')
                    ->label(__('jockeys.city'))
                    ->relationship('city', $name)
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                PendingPosts::approveAction(),
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('racingProfile')
                        ->label(__('jockeys.open_racing_profile'))
                        ->icon('heroicon-o-flag')
                        ->url(fn (Jockey $record): string => route('racing.profile', ['entity' => 'jockey', 'id' => $record->racing_jockey_id]))
                        ->openUrlInNewTab()
                        ->visible(fn (Jockey $record): bool => $record->racing_jockey_id !== null),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    PendingPosts::approveBulkAction(),
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJockeys::route('/'),
            'create' => CreateJockey::route('/create'),
            'view' => ViewJockey::route('/{record}'),
            'edit' => EditJockey::route('/{record}/edit'),
        ];
    }
}

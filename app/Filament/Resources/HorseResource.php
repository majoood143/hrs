<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HorseResource\Pages\CreateHorse;
use App\Filament\Resources\HorseResource\Pages\EditHorse;
use App\Filament\Resources\HorseResource\Pages\ListHorses;
use App\Filament\Resources\HorseResource\Pages\ViewHorse;
use App\Filament\Resources\HorseResource\RelationManagers\AttachementRelationManager;
use App\Filament\Resources\HorseResource\RelationManagers\TransactionRelationManager;
use App\Filament\Resources\HorseResource\RelationManagers\VaccinationRelationManager;
use App\Filament\Support\PlaceFormFields;
use App\Models\City;
use App\Models\Color;
use App\Models\Country;
use App\Models\Gender;
use App\Models\Horse;
use App\Models\Region;
use App\Models\Type;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mpdf\QrCode\Output\Png;
use Mpdf\QrCode\QrCode;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class HorseResource extends Resource
{
    protected static ?string $model = Horse::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getModelLabel(): string
    {
        return __('admin_horse.navigation.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin_horse.navigation.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_horse.navigation.plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::count();
    }

    /** `ar_name` in the Arabic admin, else `en_name`. */
    private static function nameColumn(): string
    {
        return app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';
    }

    private static function label(string $key): string
    {
        return __("admin_horse.fields.{$key}");
    }

    /** @param  class-string<Model>  $model */
    private static function lookup(string $field, string $model, string $label): Select
    {
        return Select::make($field)
            ->label(static::label($label))
            ->options(fn () => $model::query()->orderBy(static::nameColumn())->pluck(static::nameColumn(), 'id'))
            ->searchable()
            ->preload();
    }

    /** Another horse as dam or sire: any horse but this one, named with its gender. */
    private static function parent(string $field, string $label): Select
    {
        return Select::make($field)
            ->label(static::label($label))
            ->options(fn (?Horse $record) => Horse::query()
                ->with('gender')
                ->when($record, fn (Builder $query) => $query->whereKeyNot($record->getKey()))
                ->orderBy(static::nameColumn())
                ->get()
                ->mapWithKeys(fn (Horse $horse) => [$horse->getKey() => $horse->{static::nameColumn()}.($horse->gender ? ' · '.$horse->gender->{static::nameColumn()} : '')]))
            ->searchable();
    }

    /**
     * Country → region → city, in the admin's language.
     *
     * @return array<int, Select>
     */
    private static function locationFields(): array
    {
        return [
            Select::make('country_id')
                ->label(static::label('country'))
                ->options(fn () => Country::query()->orderBy(static::nameColumn())->pluck(static::nameColumn(), 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('region_id', null);
                    $set('city_id', null);
                })
                ->required(),
            Select::make('region_id')
                ->label(static::label('region'))
                ->options(fn (Get $get): Collection => Region::query()
                    ->where('country_id', $get('country_id'))
                    ->orderBy(static::nameColumn())
                    ->pluck(static::nameColumn(), 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                ->required(),
            Select::make('city_id')
                ->label(static::label('city'))
                ->options(fn (Get $get): Collection => City::query()
                    ->where('region_id', $get('region_id'))
                    ->orderBy(static::nameColumn())
                    ->pluck(static::nameColumn(), 'id'))
                ->searchable()
                ->required(),
        ];
    }

    /** A horse owner (`users.type = owner`); a new one can be added from the field. */
    private static function ownerField(): Select
    {
        return Select::make('user_id')
            ->label(static::label('owner'))
            ->helperText(static::label('owner_helper'))
            ->options(fn () => User::query()->where('type', 'owner')->orderBy('name')->pluck('name', 'id'))
            ->searchable()
            ->required()
            ->createOptionForm([
                TextInput::make('name')
                    ->label(static::label('owner_name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label(static::label('email'))
                    ->email()
                    ->required()
                    ->unique('users', 'email')
                    ->maxLength(255),
                TextInput::make('password')
                    ->label(static::label('password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->maxLength(255),
            ])
            // Always a horse owner, whatever the column's default becomes (the password is hashed by the model cast).
            ->createOptionUsing(fn (array $data): int => User::query()->create([...$data, 'type' => 'owner'])->getKey());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Horse')
                    ->tabs([
                        Tab::make(__('admin_horse.tabs.horse'))
                            ->schema([
                                TextInput::make('en_name')
                                    ->label(static::label('en_name'))
                                    ->helperText(static::label('en_name_helper'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('ar_name')
                                    ->label(static::label('ar_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                                TextInput::make('microchip')
                                    ->label(static::label('microchip'))
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                TextInput::make('passport_number')
                                    ->label(static::label('passport_number'))
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('registration_number')
                                    ->label(static::label('registration_number'))
                                    ->maxLength(255),
                                DatePicker::make('dob')
                                    ->label(static::label('dob'))
                                    ->maxDate(now())
                                    ->required(),
                                static::lookup('type_id', Type::class, 'type')->required(),
                                static::lookup('gender_id', Gender::class, 'gender')->required(),
                                static::lookup('color_id', Color::class, 'color'),
                                TextInput::make('breed')
                                    ->label(static::label('breed'))
                                    ->maxLength(255),
                                static::parent('dam_id', 'dam'),
                                static::parent('sire_id', 'sire')->different('dam_id'),
                            ])->icon('heroicon-o-information-circle')
                            ->columns(3),
                        Tab::make(__('admin_horse.tabs.location'))
                            ->schema(static::locationFields())
                            ->icon('heroicon-o-map-pin')
                            ->columns(3),
                        Tab::make(__('admin_horse.tabs.owner'))
                            ->schema([static::ownerField()])
                            ->icon('heroicon-o-user')
                            ->columns(2),
                        Tab::make(__('horse.website_profile.tab'))
                            ->schema([
                                Toggle::make('is_featured')
                                    ->label(__('horse.website_profile.is_featured'))
                                    ->helperText(__('horse.website_profile.is_featured_helper')),
                                PlaceFormFields::coverPhoto('horses/covers', __('horse.website_profile.cover_photo'))
                                    ->helperText(__('horse.website_profile.cover_photo_helper')),
                                Textarea::make('public_story_en')
                                    ->label(__('horse.website_profile.public_story_en'))
                                    ->helperText(__('horse.website_profile.public_story_helper'))
                                    ->rows(3),
                                Textarea::make('public_story_ar')
                                    ->label(__('horse.website_profile.public_story_ar'))
                                    ->rows(3)
                                    ->extraInputAttributes(['dir' => 'rtl']),
                            ])->icon('heroicon-o-globe-alt')
                            ->columns(2),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $name = static::nameColumn();

        return $table
            ->columns([
                TextColumn::make($name)
                    ->label(static::label('name'))
                    ->searchable(['en_name', 'ar_name', 'microchip', 'passport_number'])
                    ->description(fn (Horse $record): string => __('admin_horse.description', [
                        'microchip' => $record->microchip ?: '—',
                        'passport' => $record->passport_number ?: '—',
                    ]))
                    ->sortable(),
                TextColumn::make('age')
                    ->label(static::label('age'))
                    ->suffix(' '.static::label('years')),
                TextColumn::make('type.'.$name)
                    ->label(static::label('type'))
                    ->sortable(),
                TextColumn::make('gender.'.$name)
                    ->label(static::label('gender'))
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(static::label('owner'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city.'.$name)
                    ->label(static::label('city'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('region.'.$name)
                    ->label(static::label('region'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country.'.$name)
                    ->label(static::label('country'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(static::label('created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label(static::label('updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort($name)
            ->filters([
                SelectFilter::make('type_id')
                    ->label(static::label('type'))
                    ->relationship('type', $name)
                    ->preload(),
                SelectFilter::make('gender_id')
                    ->label(static::label('gender'))
                    ->relationship('gender', $name)
                    ->preload(),
                SelectFilter::make('country_id')
                    ->label(static::label('country'))
                    ->relationship('country', $name)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('user_id')
                    ->label(static::label('owner'))
                    ->relationship('user', 'name', fn (Builder $query) => $query->where('type', 'owner'))
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    // Drawn here: the microchip number is never sent to a third-party QR service.
                    Action::make('printQR')
                        ->label(__('admin_horse.actions.print_qr'))
                        ->icon('heroicon-o-qr-code')
                        ->color('success')
                        ->visible(fn (Horse $record): bool => filled($record->microchip))
                        ->action(fn (Horse $record) => response()->streamDownload(
                            fn () => print ((new Png)->output(new QrCode((string) $record->microchip, 'M'), 300)),
                            'horse-'.preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $record->microchip).'.png',
                            ['Content-Type' => 'image/png'],
                        )),
                    Action::make('changeOwner')
                        ->label(__('admin_horse.actions.change_owner'))
                        ->icon('heroicon-o-user-circle')
                        ->color('warning')
                        ->modalWidth('xl')
                        ->modalHeading(fn (Horse $record) => __('admin_horse.actions.change_owner_heading', ['name' => $record->{static::nameColumn()}]))
                        ->schema([
                            Section::make(__('admin_horse.actions.current_owner'))
                                ->schema([
                                    Placeholder::make('current_owner')
                                        ->label(static::label('owner'))
                                        ->content(fn (Horse $record) => $record->user?->name ?? static::label('unassigned')),
                                    Placeholder::make('current_email')
                                        ->label(static::label('email'))
                                        ->content(fn (Horse $record) => $record->user?->email ?? static::label('unassigned')),
                                    Placeholder::make('current_phone')
                                        ->label(static::label('mobile'))
                                        ->content(fn (Horse $record) => $record->user?->phone ?? static::label('unassigned')),
                                ])
                                ->columns(3),
                            Section::make(__('admin_horse.actions.new_owner'))
                                ->schema([static::ownerField()]),
                        ])
                        ->action(fn (array $data, Horse $record) => $record->update(['user_id' => $data['user_id']]))
                        ->successNotificationTitle(__('admin_horse.actions.owner_changed')),
                    Action::make('exportHorse')
                        ->label(__('admin_horse.actions.move'))
                        ->icon('heroicon-o-arrow-right-end-on-rectangle')
                        ->schema([
                            Section::make(__('admin_horse.actions.current_location'))
                                ->schema([
                                    Placeholder::make('current_location')
                                        ->hiddenLabel()
                                        ->content(fn (Horse $record) => collect([$record->city, $record->region, $record->country])
                                            ->filter()
                                            ->map(fn ($place) => $place->{static::nameColumn()})
                                            ->implode(', ') ?: static::label('unassigned')),
                                ]),
                            Section::make(__('admin_horse.actions.new_location'))
                                ->schema(static::locationFields())
                                ->columns(3),
                        ])
                        ->action(fn (array $data, Horse $record) => $record->update([
                            'country_id' => $data['country_id'],
                            'region_id' => $data['region_id'],
                            'city_id' => $data['city_id'],
                        ]))
                        ->successNotificationTitle(__('admin_horse.actions.moved')),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AttachementRelationManager::class,
            VaccinationRelationManager::class,
            TransactionRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHorses::route('/'),
            'create' => CreateHorse::route('/create'),
            'view' => ViewHorse::route('/{record}'),
            'edit' => EditHorse::route('/{record}/edit'),
        ];
    }
}

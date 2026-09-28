<?php

namespace App\Filament\Stable\Schemas;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Stable;
use App\Models\StableService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * The stable's own details as its owner edits them: at sign-up, when adding another stable, and on
 * the stable profile page. The slug is never here (it is made once from the English name), and
 * neither is anything the admins decide (approval, commission).
 */
final class StableProfileForm
{
    private static function nameColumn(): string
    {
        return app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';
    }

    /** @return array<int, mixed> */
    public static function names(): array
    {
        return [
            TextInput::make('en_name')
                ->label(__('stable_panel.fields.en_name'))
                ->required()
                ->maxLength(255),
            TextInput::make('ar_name')
                ->label(__('stable_panel.fields.ar_name'))
                ->required()
                ->maxLength(255)
                ->extraInputAttributes(['dir' => 'rtl']),
        ];
    }

    /** @return array<int, mixed> */
    public static function contact(): array
    {
        return [
            TextInput::make('phone')
                ->label(__('stable_panel.fields.stable_phone'))
                ->tel()
                ->maxLength(32),
            TextInput::make('email')
                ->label(__('stable_panel.fields.stable_email'))
                ->email()
                ->maxLength(255),
        ];
    }

    /** @return array<int, mixed> */
    public static function location(): array
    {
        return [
            Select::make('country_id')
                ->label(__('stable_panel.fields.country'))
                ->options(fn () => Country::query()->public()->orderBy(self::nameColumn())->pluck(self::nameColumn(), 'id'))
                ->default(fn () => Country::query()->public()->where('en_name', 'Oman')->value('id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('region_id', null);
                    $set('city_id', null);
                })
                ->required(),
            Select::make('region_id')
                ->label(__('stable_panel.fields.region'))
                ->options(fn (Get $get) => Region::query()->where('country_id', $get('country_id'))->orderBy(self::nameColumn())->pluck(self::nameColumn(), 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                ->required(),
            Select::make('city_id')
                ->label(__('stable_panel.fields.city'))
                ->options(fn (Get $get) => City::query()->where('region_id', $get('region_id'))->orderBy(self::nameColumn())->pluck(self::nameColumn(), 'id'))
                ->searchable()
                ->required(),
            TextInput::make('address')
                ->label(__('stable_panel.fields.address'))
                ->maxLength(255),
            TextInput::make('map_link')
                ->label(__('stable_panel.fields.map_link'))
                ->url()
                ->maxLength(255),
        ];
    }

    /** @return array<int, mixed> */
    public static function description(): array
    {
        return [
            Textarea::make('en_description')
                ->label(__('stable_panel.fields.en_description'))
                ->rows(5),
            Textarea::make('ar_description')
                ->label(__('stable_panel.fields.ar_description'))
                ->rows(5)
                ->extraInputAttributes(['dir' => 'rtl']),
        ];
    }

    /** @return array<int, mixed> */
    public static function amenities(): array
    {
        return [
            Select::make('services')
                ->label(__('stable_panel.fields.amenities'))
                ->relationship('services', self::nameColumn())
                ->getOptionLabelFromRecordUsing(fn (StableService $record) => $record->name)
                ->multiple()
                ->preload(),
        ];
    }

    /** @return array<int, mixed> */
    public static function photos(): array
    {
        return [
            FileUpload::make('cover_photo')
                ->label(__('stable_panel.fields.cover_photo'))
                ->image()
                ->disk('public')
                ->directory('stables/covers'),
            FileUpload::make('gallery')
                ->label(__('stable_panel.fields.gallery'))
                ->image()
                ->multiple()
                ->reorderable()
                ->maxFiles(20)
                ->disk('public')
                ->directory('stables/gallery'),
        ];
    }

    /** @return array<int, mixed> */
    public static function openingHours(): array
    {
        return collect(Stable::DAYS)->map(
            fn (string $day) => Fieldset::make(__('stable_panel.days.'.$day))
                ->schema([
                    Toggle::make("opening_hours.{$day}.closed")
                        ->label(__('stable_panel.fields.closed'))
                        ->live(),
                    TimePicker::make("opening_hours.{$day}.opens_at")
                        ->label(__('stable_panel.fields.opens_at'))
                        ->seconds(false)
                        ->hidden(fn (Get $get) => (bool) $get("opening_hours.{$day}.closed")),
                    TimePicker::make("opening_hours.{$day}.closes_at")
                        ->label(__('stable_panel.fields.closes_at'))
                        ->seconds(false)
                        ->hidden(fn (Get $get) => (bool) $get("opening_hours.{$day}.closed")),
                ])
                ->columns(3)
        )->all();
    }
}

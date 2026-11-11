<?php

namespace App\Filament\Stable\Schemas;

use App\Filament\Support\PlaceFormFields;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\Stable;
use App\Models\StableService;
use Filament\Forms\Components\Select;
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
                ->placeholder(__('stable_panel.placeholders.en_name'))
                ->required()
                ->maxLength(255),
            TextInput::make('ar_name')
                ->label(__('stable_panel.fields.ar_name'))
                ->placeholder(__('stable_panel.placeholders.ar_name'))
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
                ->placeholder(__('stable_panel.placeholders.stable_phone'))
                ->tel()
                ->maxLength(32),
            TextInput::make('email')
                ->label(__('stable_panel.fields.stable_email'))
                ->placeholder(__('stable_panel.placeholders.stable_email'))
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
                ->placeholder(__('stable_panel.placeholders.country'))
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
                ->placeholder(__('stable_panel.placeholders.region'))
                ->options(fn (Get $get) => Region::query()->where('country_id', $get('country_id'))->orderBy(self::nameColumn())->pluck(self::nameColumn(), 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                ->required(),
            Select::make('city_id')
                ->label(__('stable_panel.fields.city'))
                ->placeholder(__('stable_panel.placeholders.city'))
                ->options(fn (Get $get) => City::query()->where('region_id', $get('region_id'))->orderBy(self::nameColumn())->pluck(self::nameColumn(), 'id'))
                ->searchable()
                ->required(),
            TextInput::make('address')
                ->label(__('stable_panel.fields.address'))
                ->placeholder(__('stable_panel.placeholders.address'))
                ->maxLength(255),
            TextInput::make('map_link')
                ->label(__('stable_panel.fields.map_link'))
                ->placeholder(__('stable_panel.placeholders.map_link'))
                ->url()
                ->rule('url:http,https')
                ->maxLength(255),
        ];
    }

    /** @return array<int, mixed> */
    public static function description(): array
    {
        [$en, $ar] = PlaceFormFields::descriptions(__('stable_panel.fields.en_description'), __('stable_panel.fields.ar_description'));

        return [
            $en->placeholder(__('stable_panel.placeholders.en_description')),
            $ar->placeholder(__('stable_panel.placeholders.ar_description')),
        ];
    }

    /** @return array<int, mixed> */
    public static function amenities(): array
    {
        return [
            Select::make('services')
                ->label(__('stable_panel.fields.amenities'))
                ->placeholder(__('stable_panel.placeholders.amenities'))
                ->relationship('services', self::nameColumn())
                ->getOptionLabelFromRecordUsing(fn (StableService $record) => $record->name)
                ->multiple()
                ->preload()
                ->rule(self::keepsOfferedServices()),
        ];
    }

    /** The services select's rule: a service one of the stable's active offerings is sold under stays ticked. */
    public static function keepsOfferedServices(): \Closure
    {
        return fn (?Stable $record) => function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
            if (! $record?->exists) {
                return;
            }

            $missing = StableService::query()
                ->whereIn('id', $record->offerings()->where('is_active', true)->select('stable_service_id'))
                ->whereNotIn('id', (array) $value)
                ->get();

            if ($missing->isNotEmpty()) {
                $fail(__('stable_panel.offerings.service_in_use', ['services' => $missing->pluck('name')->join(app()->getLocale() === 'ar' ? '، ' : ', ')]));
            }
        };
    }

    /** @return array<int, mixed> */
    public static function photos(): array
    {
        return [
            PlaceFormFields::coverPhoto('stables/covers', __('stable_panel.fields.cover_photo')),
            PlaceFormFields::gallery('stables/gallery', __('stable_panel.fields.gallery')),
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

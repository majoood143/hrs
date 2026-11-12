<?php

namespace App\Filament\Support;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Collection;

/**
 * Country → region → city, each list narrowed by the one before and cleared when it changes,
 * names in the admin's language. Used by every admin form with a location.
 */
final class LocationFields
{
    /**
     * @param  Closure(string): string  $label  the label of `country`, `region` and `city` in the form's own words
     * @param  string  $prefix  for a second location in the same form (`from_`, `to_`)
     * @return list<Select>
     */
    public static function make(Closure $label, string $prefix = ''): array
    {
        return [
            Select::make("{$prefix}country_id")
                ->label($label('country'))
                ->options(fn () => Country::query()->orderBy(self::nameColumn())->pluck(self::nameColumn(), 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(function (Set $set) use ($prefix): void {
                    $set("{$prefix}region_id", null);
                    $set("{$prefix}city_id", null);
                })
                ->required(),
            Select::make("{$prefix}region_id")
                ->label($label('region'))
                ->options(fn (Get $get): Collection => Region::query()
                    ->where('country_id', $get("{$prefix}country_id"))
                    ->orderBy(self::nameColumn())
                    ->pluck(self::nameColumn(), 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set("{$prefix}city_id", null))
                ->required(),
            Select::make("{$prefix}city_id")
                ->label($label('city'))
                ->options(fn (Get $get): Collection => City::query()
                    ->where('region_id', $get("{$prefix}region_id"))
                    ->orderBy(self::nameColumn())
                    ->pluck(self::nameColumn(), 'id'))
                ->searchable()
                ->required(),
        ];
    }

    /** `ar_name` in the Arabic admin, else `en_name`. */
    public static function nameColumn(): string
    {
        return app()->getLocale() === 'ar' ? 'ar_name' : 'en_name';
    }
}

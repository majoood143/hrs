<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Grid;

class TranslatableInput
{
    /**
     * @return array<string, array{name: string, native: string, dir: string}>
     */
    public static function locales(): array
    {
        return config('languages.available', [
            'en' => ['name' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        ]);
    }

    public static function defaultLocale(): string
    {
        return config('languages.default', 'en');
    }

    /**
     * Build a grid containing one field per configured language for a given
     * base field name (e.g. "heading" produces "heading.en", "heading.ar", ...).
     * Adding a language to config/languages.php grows this grid automatically
     * — no resource code changes are needed.
     *
     * @param  callable(string $locale, array{name: string, native: string, dir: string} $meta): Field  $factory
     */
    public static function grid(callable $factory, ?int $columns = null): Grid
    {
        $locales = static::locales();
        $components = [];

        foreach ($locales as $code => $meta) {
            $component = $factory($code, $meta);

            if (($meta['dir'] ?? 'ltr') === 'rtl') {
                $component->extraInputAttributes(['dir' => 'rtl']);
            }

            $components[] = $component;
        }

        return Grid::make($columns ?? min(count($locales), 2))->schema($components);
    }
}

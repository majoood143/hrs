<?php

namespace App\Services\Exports;

use BackedEnum;
use DateTimeInterface;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Support\Contracts\HasLabel;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Stringable;
use Throwable;
use UnitEnum;

/**
 * Turns what a Filament table column, form field or infolist entry shows into plain text for an
 * export (PDF cell or CSV value): enum labels, option labels instead of ids, yes/no for booleans,
 * the currency icon's alt text instead of its <img>, file names instead of paths. Anything a
 * resource's formatting closure chokes on falls back to the raw state instead of failing the export.
 */
class ExportValue
{
    public static function exportsColumn(Column $column): bool
    {
        return ! $column instanceof ImageColumn;
    }

    public static function column(Column $column): string
    {
        $state = $column->getState();

        if ($column instanceof ToggleColumn || $column instanceof CheckboxColumn || ($column instanceof IconColumn && $column->isBoolean())) {
            return self::bool($state);
        }

        if ($column instanceof SelectColumn) {
            return self::fromOptions($state, $column->getOptions());
        }

        return self::formatted($column, $state);
    }

    public static function exportsField(Field $field): bool
    {
        return ! ($field instanceof Hidden || ($field instanceof TextInput && $field->isPassword()));
    }

    public static function field(Field $field): string
    {
        try {
            $state = $field->getState();

            return match (true) {
                $field instanceof Toggle, $field instanceof Checkbox => self::bool($state),
                $field instanceof Select => $field->isMultiple() ? self::text($field->getOptionLabels()) : self::text($field->getOptionLabel()),
                $field instanceof CheckboxList, $field instanceof Radio, $field instanceof ToggleButtons => self::fromOptions($state, $field->getOptions()),
                $field instanceof BaseFileUpload => self::text(array_map(fn (array $file) => $file['name'] ?? '', $field->getUploadedFiles() ?? [])),
                $field instanceof RichEditor && is_array($state) => RichContentRenderer::make($state)->toText(),
                default => self::text($state),
            };
        } catch (Throwable $e) {
            report($e);

            return '';
        }
    }

    public static function exportsEntry(Entry $entry): bool
    {
        return ! $entry instanceof ImageEntry;
    }

    public static function entry(Entry $entry): string
    {
        try {
            $state = $entry->getState();

            if ($entry instanceof IconEntry && $entry->isBoolean()) {
                return self::bool($state);
            }

            return self::formatted($entry, $state);
        } catch (Throwable $e) {
            report($e);

            return '';
        }
    }

    /** Anything as a plain, single string: lists become "a, b", HTML becomes its text. */
    public static function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => self::bool($value),
            $value instanceof HasLabel => self::text($value->getLabel()),
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Htmlable => self::fromHtml($value->toHtml()),
            $value instanceof DateTimeInterface => $value->format($value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i'),
            $value instanceof Collection => self::text($value->all()),
            $value instanceof Model => (string) $value->getKey(),
            is_array($value) => self::fromArray($value),
            is_scalar($value), $value instanceof Stringable => self::fromHtml((string) $value),
            default => '',
        };
    }

    public static function bool(mixed $state): string
    {
        return __($state ? 'admin_export.yes' : 'admin_export.no');
    }

    /** Strip markup, keeping an <img>'s alt text (the currency icon is an image with the currency code as its alt). */
    public static function fromHtml(string $html): string
    {
        if (! str_contains($html, '<') && ! str_contains($html, '&')) {
            return trim($html);
        }

        $html = preg_replace('/<img\b[^>]*\balt\s*=\s*"([^"]*)"[^>]*>/i', '$1', $html) ?? $html;
        $html = preg_replace('/<(br|\/p|\/div|\/li|\/tr|\/h[1-6])\b[^>]*>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text) ?? $text;

        return trim(preg_replace("/\s*\n\s*/", "\n", $text) ?? $text);
    }

    /** The column / entry's own formatting (enum labels, dates, money, limits), item by item for lists. */
    private static function formatted(object $component, mixed $state): string
    {
        $items = $state instanceof Collection ? $state->all() : (is_array($state) && array_is_list($state) ? $state : [$state]);

        $parts = array_map(function (mixed $item) use ($component): string {
            if (! method_exists($component, 'formatState')) {
                return self::text($item);
            }

            try {
                return self::text($component->formatState($item));
            } catch (Throwable) {
                return self::text($item);
            }
        }, $items);

        return implode(', ', array_filter($parts, fn (string $part) => $part !== ''));
    }

    /** @param  array<array-key, mixed>  $options */
    private static function fromOptions(mixed $state, array $options): string
    {
        $flat = [];

        array_walk_recursive($options, function (mixed $label, int|string $value) use (&$flat) {
            $flat[(string) $value] = $label;
        });

        $values = is_array($state) ? $state : [$state];
        $labels = array_map(function (mixed $value) use ($flat) {
            $key = $value instanceof BackedEnum ? (string) $value->value : self::text($value);

            return self::text($flat[$key] ?? $value);
        }, $values);

        return implode(', ', array_filter($labels, fn (string $label) => $label !== ''));
    }

    /** @param  array<array-key, mixed>  $value */
    private static function fromArray(array $value): string
    {
        if (array_is_list($value)) {
            return implode(', ', array_filter(array_map(self::text(...), $value), fn (string $part) => $part !== ''));
        }

        $lines = [];

        foreach ($value as $key => $item) {
            $lines[] = $key.': '.self::text($item);
        }

        return implode("\n", $lines);
    }
}

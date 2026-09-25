<?php

namespace App\Services\Exports;

use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * What an admin "Export PDF / CSV" writes, already in the export's language: a list page's table
 * (headings + rows, read lazily so a CSV of every record doesn't load them all at once) or a view
 * page's details (label / value pairs under their section headings).
 */
class ExportDocument
{
    public const TABLE = 'table';

    public const DETAILS = 'details';

    /**
     * @param  array<string, string>  $facts  label => value lines under the title
     * @param  list<string>  $headings  table layout only
     * @param  Closure(?int): iterable<int, list<string>|array{heading?: string, label?: string, value?: string}>  $rows  given an optional row limit
     */
    public function __construct(
        public readonly string $layout,
        public readonly string $title,
        public readonly ?string $subtitle,
        public readonly array $facts,
        public readonly array $headings,
        public readonly Closure $rows,
        public readonly int $total,
    ) {}

    /** @return iterable<int, list<string>|array{heading?: string, label?: string, value?: string}> */
    public function rows(?int $limit = null): iterable
    {
        return ($this->rows)($limit);
    }

    /** @param  array<string, string>  $facts */
    public static function details(string $title, ?string $subtitle, array $facts, Schema $schema): self
    {
        $rows = self::walk($schema);

        return new self(self::DETAILS, $title, $subtitle, $facts, [], fn () => $rows, count($rows));
    }

    /**
     * A schema's visible fields / entries in reading order, with a heading row for every titled
     * section, fieldset or tab. A repeater's items become one value ("label: value" per line).
     *
     * @return list<array{heading?: string, label?: string, value?: string}>
     */
    public static function walk(Schema $schema): array
    {
        $rows = [];

        foreach ($schema->getComponents(withActions: false) as $component) {
            if (! $component instanceof Component) {
                continue;
            }

            if ($component instanceof Field || $component instanceof Entry) {
                if ($row = self::valueRow($component)) {
                    $rows[] = $row;
                }

                continue;
            }

            $heading = match (true) {
                $component instanceof Section => $component->getHeading(),
                $component instanceof Fieldset, $component instanceof Tab, $component instanceof Step => $component->getLabel(),
                default => null,
            };

            $children = [];

            foreach ($component->getChildSchemas() as $childSchema) {
                array_push($children, ...self::walk($childSchema));
            }

            if ($children === []) {
                continue;
            }

            $heading = ExportValue::text($heading);

            if ($heading !== '') {
                $rows[] = ['heading' => $heading];
            }

            array_push($rows, ...$children);
        }

        return $rows;
    }

    /** @return ?array{label: string, value: string} */
    private static function valueRow(Field|Entry $component): ?array
    {
        if ($component instanceof Field && ! ExportValue::exportsField($component)) {
            return null;
        }

        if ($component instanceof Entry && ! ExportValue::exportsEntry($component)) {
            return null;
        }

        $label = ExportValue::text($component->getLabel());

        if ($label === '') {
            $label = Str::headline($component->getName());
        }

        if ($component instanceof Repeater || $component instanceof Builder || $component instanceof RepeatableEntry) {
            $items = [];

            foreach ($component->getItems() as $item) {
                $lines = array_map(
                    fn (array $row) => isset($row['heading']) ? $row['heading'] : $row['label'].': '.$row['value'],
                    self::walk($item),
                );
                $items[] = implode("\n", $lines);
            }

            return ['label' => $label, 'value' => implode("\n\n", array_filter($items))];
        }

        return ['label' => $label, 'value' => $component instanceof Field ? ExportValue::field($component) : ExportValue::entry($component)];
    }
}

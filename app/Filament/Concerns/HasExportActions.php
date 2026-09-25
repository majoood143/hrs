<?php

namespace App\Filament\Concerns;

use App\Filament\Support\TranslatableInput;
use App\Services\Exports\ExportCsv;
use App\Services\Exports\ExportDocument;
use App\Services\Exports\ExportPdf;
use App\Services\Exports\ExportValue;
use App\Support\Locale;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * "PDF" and "CSV" header buttons on a resource's list or view page, each with one item per
 * language (config/languages.php). Add `use HasExportActions;` to the page class; nothing else.
 *
 * The list export is the table as the admin sees it: the active tab, filters, search and sort, and
 * the visible columns. The view export is the record's details, read from the page's own infolist
 * (or form), section by section. Both are rebuilt inside the chosen language, so labels, enum
 * names and translatable values come out in it whatever language the admin panel is in.
 *
 * Filament calls cacheHasExportActions() itself (it runs `cache{Trait}` for every page trait whose
 * name ends in "Actions"), after the page's own header actions, so getHeaderActions() stays as is.
 */
trait HasExportActions
{
    public function cacheHasExportActions(): void
    {
        if (! $this instanceof ListRecords && ! $this instanceof ViewRecord) {
            return;
        }

        $groups = [$this->exportActionGroup('pdf'), $this->exportActionGroup('csv')];

        foreach ($groups as $group) {
            $group->livewire($this)->dropdownPlacement('bottom-end');
            $this->mergeCachedActions($group->getFlatActions());
        }

        $this->cachedHeaderActions = [...$groups, ...$this->cachedHeaderActions];
    }

    protected function exportActionGroup(string $format): ActionGroup
    {
        $actions = [];

        foreach (TranslatableInput::locales() as $code => $meta) {
            $actions[] = Action::make('export'.Str::studly($format).Str::studly($code))
                ->label(Lang::has("admin_export.languages.{$code}") ? __("admin_export.languages.{$code}") : $meta['name'])
                ->icon($format === 'pdf' ? 'heroicon-o-document-arrow-down' : 'heroicon-o-table-cells')
                ->action(fn (): StreamedResponse => $this->downloadExport($format, $code));
        }

        return ActionGroup::make($actions)
            ->label(__("admin_export.{$format}"))
            ->icon($format === 'pdf' ? 'heroicon-o-document-arrow-down' : 'heroicon-o-table-cells')
            ->color('gray')
            ->button();
    }

    protected function downloadExport(string $format, string $locale): StreamedResponse
    {
        $filename = $this->exportFilename($locale).'.'.$format;

        return response()->streamDownload(function () use ($format, $locale): void {
            Locale::within($locale, function () use ($format): void {
                $document = $this instanceof ViewRecord ? $this->viewExportDocument() : $this->listExportDocument();

                if ($format === 'pdf') {
                    echo app(ExportPdf::class)->render($document);

                    return;
                }

                $out = fopen('php://output', 'wb');
                app(ExportCsv::class)->write($out, $document);
                fclose($out);
            });
        }, $filename, ['Content-Type' => $format === 'pdf' ? 'application/pdf' : 'text/csv; charset=UTF-8']);
    }

    /** "horse-sale-posts-ar-2026-09-25" / "horse-12-en-2026-09-25": from the class name, never a (possibly Arabic) label. */
    protected function exportFilename(string $locale): string
    {
        $model = class_basename(static::getResource()::getModel());
        $name = $this instanceof ViewRecord
            ? Str::kebab($model).'-'.Str::slug((string) $this->getRecord()->getKey())
            : Str::kebab(Str::pluralStudly($model));

        return $name.'-'.$locale.'-'.now()->format('Y-m-d');
    }

    protected function listExportDocument(): ExportDocument
    {
        $table = $this->table($this->makeTable());

        /** @var list<Column> $columns */
        $columns = array_values(array_filter($table->getVisibleColumns(), ExportValue::exportsColumn(...)));
        $query = $this->getFilteredSortedTableQuery();
        $total = (clone $query)->toBase()->getCountForPagination();

        $facts = [
            __('admin_export.generated') => now()->format('Y-m-d H:i'),
            __('admin_export.records') => (string) $total,
        ];

        if (filled($this->activeTab) && ($tab = $this->getTabs()[$this->activeTab] ?? null)) {
            $facts[__('admin_export.tab')] = ExportValue::text($tab->getLabel()) ?: Str::headline($this->activeTab);
        }

        if (filled($search = $this->getTableSearch())) {
            $facts[__('admin_export.search')] = $search;
        }

        return new ExportDocument(
            layout: ExportDocument::TABLE,
            title: ExportValue::text($this->getTitle()),
            subtitle: null,
            facts: $facts,
            headings: array_map(fn (Column $column) => ExportValue::text($column->getLabel()), $columns),
            rows: fn (?int $limit) => $this->exportTableRows(clone $query, $columns, $limit),
            total: $total,
        );
    }

    /**
     * @param  list<Column>  $columns
     * @return \Generator<int, list<string>>
     */
    protected function exportTableRows(Builder $query, array $columns, ?int $limit): \Generator
    {
        if ($limit !== null) {
            $records = $query->limit($limit)->get();
        } else {
            if (empty($query->getQuery()->orders)) {
                $query->orderBy($query->getModel()->getQualifiedKeyName());
            }

            $records = $query->lazy(250);
        }

        foreach ($records as $record) {
            $row = [];

            foreach ($columns as $column) {
                $column->record($record);

                try {
                    $row[] = ExportValue::column($column);
                } catch (Throwable $e) {
                    report($e);
                    $row[] = '';
                }

                // the state is cached per record; a CSV of every row would otherwise keep them all
                $column->clearCachedState();
            }

            yield $row;
        }
    }

    protected function viewExportDocument(): ExportDocument
    {
        $schema = $this->hasInfolist()
            ? $this->infolist($this->defaultInfolist($this->makeSchema()))->key('infolist')
            : $this->form($this->defaultForm($this->makeSchema()))->key('form');

        $title = ExportValue::text(static::getResource()::getTitleCaseModelLabel());
        // a resource without a record title attribute answers with its model label again
        $subtitle = ExportValue::text($this->getRecordTitle());

        return ExportDocument::details(
            title: $title,
            subtitle: $subtitle === $title ? null : $subtitle,
            facts: [__('admin_export.generated') => now()->format('Y-m-d H:i')],
            schema: $schema,
        );
    }
}

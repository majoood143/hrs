<?php

namespace App\Filament\Pages;

use App\Models\Stable;
use App\Services\Reports\IncomeStatement;
use App\Services\Reports\StableStatement;
use App\Services\Reports\StableStatementExport;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Url;

/** One stable's statement for the admins (the same statement its owner sees), to download and send. */
class StableStatementReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.stable-statement-report';

    #[Url]
    public ?int $stable = null;

    public ?array $data = [];

    public function getTitle(): string
    {
        return __('stable_statement.title').($this->record() ? ' — '.$this->record()->en_name : '');
    }

    public function mount(): void
    {
        $this->form->fill(['stable_id' => $this->stable, 'period' => 'this_month', 'date_from' => null, 'date_to' => null, 'language' => app()->getLocale()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])->schema([
                    Select::make('stable_id')
                        ->label(__('stable_statement.fields.stable'))
                        ->options(fn () => Stable::query()->orderBy('en_name')->pluck('en_name', 'id'))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn ($state) => $this->stable = $state ? (int) $state : null),
                    Select::make('period')
                        ->label(__('admin_income_report.filters.period'))
                        ->options(collect(IncomeStatement::PERIODS)->mapWithKeys(fn (string $p) => [$p => __('admin_income_report.periods.'.$p)])->all())
                        ->selectablePlaceholder(false)
                        ->native(false)
                        ->live(),
                    DatePicker::make('date_from')->label(__('admin_income_report.filters.date_from'))->visible(fn ($get) => $get('period') === 'custom')->live(),
                    DatePicker::make('date_to')->label(__('admin_income_report.filters.date_to'))->visible(fn ($get) => $get('period') === 'custom')->live(),
                    Select::make('language')
                        ->label(__('admin_income_report.filters.language'))
                        ->options(collect(config('languages.available', []))->mapWithKeys(fn (array $meta, string $code) => [$code => $meta['name']])->all())
                        ->selectablePlaceholder(false)
                        ->native(false),
                ]),
            ]);
    }

    public function record(): ?Stable
    {
        $id = $this->data['stable_id'] ?? $this->stable;

        return $id ? Stable::query()->find($id) : null;
    }

    public function statement(): ?StableStatement
    {
        $stable = $this->record();

        return $stable ? StableStatement::forPeriod($stable, (string) ($this->data['period'] ?? 'this_month'), $this->data['date_from'] ?? null, $this->data['date_to'] ?? null) : null;
    }

    private function language(): string
    {
        $chosen = (string) ($this->data['language'] ?? '');

        return array_key_exists($chosen, config('languages.available', [])) ? $chosen : app()->getLocale();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label(__('admin_income_report.actions.pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->disabled(fn () => ! $this->record())
                ->action(function () {
                    $statement = $this->statement();
                    $export = app(StableStatementExport::class);

                    return Response::streamDownload(fn () => print ($export->pdf($statement, $this->language())), $export->filename($statement, 'pdf'), ['Content-Type' => 'application/pdf']);
                }),
            Action::make('downloadCsv')
                ->label(__('admin_income_report.actions.csv'))
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->disabled(fn () => ! $this->record())
                ->action(function () {
                    $statement = $this->statement();
                    $export = app(StableStatementExport::class);
                    $language = $this->language();

                    return Response::streamDownload(fn () => $export->csv(fopen('php://output', 'w'), $statement, $language), $export->filename($statement, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}

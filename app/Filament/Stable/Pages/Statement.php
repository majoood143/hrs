<?php

namespace App\Filament\Stable\Pages;

use App\Models\Stable;
use App\Services\Reports\IncomeStatement;
use App\Services\Reports\StableStatement;
use App\Services\Reports\StableStatementExport;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;

/**
 * The stable's account with us: its bookings with the commission and its share of each, what we
 * paid it or it paid us, and where the balance stands. Downloadable as a PDF and a CSV.
 */
class Statement extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.stable.pages.statement';

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('stable_panel.navigation.bookings');
    }

    public static function getNavigationLabel(): string
    {
        return __('stable_statement.title');
    }

    public function getTitle(): string
    {
        return __('stable_statement.title');
    }

    public static function canAccess(): bool
    {
        $stable = Filament::getTenant();

        return $stable instanceof Stable && Gate::allows('update', $stable);
    }

    public function mount(): void
    {
        $this->form->fill(['period' => 'this_month', 'date_from' => null, 'date_to' => null, 'language' => app()->getLocale()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                    Select::make('period')
                        ->label(__('admin_income_report.filters.period'))
                        ->options(collect(IncomeStatement::PERIODS)->mapWithKeys(fn (string $p) => [$p => __('admin_income_report.periods.'.$p)])->all())
                        ->selectablePlaceholder(false)
                        ->native(false)
                        ->live(),
                    DatePicker::make('date_from')
                        ->label(__('admin_income_report.filters.date_from'))
                        ->visible(fn ($get) => $get('period') === 'custom')
                        ->live(),
                    DatePicker::make('date_to')
                        ->label(__('admin_income_report.filters.date_to'))
                        ->visible(fn ($get) => $get('period') === 'custom')
                        ->live(),
                    Select::make('language')
                        ->label(__('admin_income_report.filters.language'))
                        ->options(collect(config('languages.available', []))->mapWithKeys(fn (array $meta, string $code) => [$code => $meta['name']])->all())
                        ->selectablePlaceholder(false)
                        ->native(false),
                ]),
            ]);
    }

    public function statement(): StableStatement
    {
        /** @var Stable $stable */
        $stable = Filament::getTenant();

        return StableStatement::forPeriod($stable, (string) ($this->data['period'] ?? 'this_month'), $this->data['date_from'] ?? null, $this->data['date_to'] ?? null);
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
                ->action(function () {
                    $statement = $this->statement();
                    $export = app(StableStatementExport::class);

                    return Response::streamDownload(fn () => print ($export->pdf($statement, $this->language())), $export->filename($statement, 'pdf'), ['Content-Type' => 'application/pdf']);
                }),
            Action::make('downloadCsv')
                ->label(__('admin_income_report.actions.csv'))
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(function () {
                    $statement = $this->statement();
                    $export = app(StableStatementExport::class);
                    $language = $this->language();

                    return Response::streamDownload(fn () => $export->csv(fopen('php://output', 'w'), $statement, $language), $export->filename($statement, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}

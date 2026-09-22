<?php

namespace App\Filament\Pages;

use App\Enums\PaymentGateway;
use App\Models\Service;
use App\Services\Reports\IncomeStatement;
use App\Services\Reports\StatementCsv;
use App\Services\Reports\StatementPdf;
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

/**
 * What the orders paid in a period brought in: what customers paid, what belongs to the client and
 * what the client owes us (service fees, the VAT on them, commission). Downloadable as a PDF statement
 * (to send to the client) and as a CSV.
 */
class IncomeReport extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.income-report';

    public ?array $data = [];

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.payments');
    }

    public function getTitle(): string
    {
        return __('admin_income_report.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin_income_report.title');
    }

    public function mount(): void
    {
        $this->form->fill([
            'period' => 'this_month',
            'date_from' => null,
            'date_to' => null,
            'service_id' => null,
            'gateway' => null,
            'language' => app()->getLocale(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 6])
                    ->schema([
                        Select::make('period')
                            ->label(__('admin_income_report.filters.period'))
                            ->options(collect(IncomeStatement::PERIODS)->mapWithKeys(fn (string $period) => [$period => __('admin_income_report.periods.'.$period)])->all())
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

                        Select::make('service_id')
                            ->label(__('admin_income_report.filters.service'))
                            ->options(fn () => Service::query()->orderBy('name')->get()->mapWithKeys(fn (Service $service) => [$service->getKey() => $service->localizedName()])->all())
                            ->placeholder(__('admin_income_report.filters.all_services'))
                            ->searchable()
                            ->live(),

                        Select::make('gateway')
                            ->label(__('admin_income_report.filters.gateway'))
                            ->options(collect(PaymentGateway::cases())->mapWithKeys(fn (PaymentGateway $gateway) => [$gateway->value => $gateway->label()])->all())
                            ->placeholder(__('admin_income_report.filters.all_gateways'))
                            ->native(false)
                            ->live(),

                        Select::make('language')
                            ->label(__('admin_income_report.filters.language'))
                            ->options(collect(config('languages.available', []))->mapWithKeys(fn (array $meta, string $code) => [$code => $meta['name']])->all())
                            ->helperText(__('admin_income_report.filters.language_helper'))
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live(),
                    ]),
            ])
            ->statePath('data');
    }

    public function statement(): IncomeStatement
    {
        return IncomeStatement::forPeriod(
            (string) ($this->data['period'] ?? 'this_month'),
            $this->data['date_from'] ?? null,
            $this->data['date_to'] ?? null,
            filled($this->data['service_id'] ?? null) ? (int) $this->data['service_id'] : null,
            filled($this->data['gateway'] ?? null) ? (string) $this->data['gateway'] : null,
        );
    }

    /** The language of a download: the one picked here, if we have it. */
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
                    $pdf = app(StatementPdf::class);

                    return Response::streamDownload(
                        fn () => print ($pdf->render($statement, $this->language())),
                        $pdf->filename($statement),
                        ['Content-Type' => 'application/pdf'],
                    );
                }),

            Action::make('downloadCsv')
                ->label(__('admin_income_report.actions.csv'))
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(function () {
                    $statement = $this->statement();
                    $language = $this->language();

                    return Response::streamDownload(
                        fn () => app(StatementCsv::class)->write(fopen('php://output', 'w'), $statement, $language),
                        'income-statement-'.$statement->from->toDateString().'_'.$statement->to->toDateString().'.csv',
                        ['Content-Type' => 'text/csv; charset=UTF-8'],
                    );
                }),
        ];
    }
}

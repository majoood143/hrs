<?php

namespace App\Filament\Forms;

use App\Enums\OrderStatus;
use App\Filament\Pages\IncomeReport;
use App\Services\Forms\FormInsights;
use App\Services\Forms\FormInsightsExport;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Response;
use Packstub\FormBuilder\FormBuilderPlugin;
use Packstub\FormBuilder\Models\Form;

/**
 * What one form's submissions say: how many came in and when, how every choice / tick box /
 * nationality / number / date field was answered, and for a form linked to a paid service its orders
 * and money. Registered on the package's Forms resource (`FormBuilder::registerResourcePage()`), so
 * whoever may view a form may open it; the money needs the Income report permission as well.
 */
class ViewFormInsights extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithRecord;

    protected string $view = 'filament.pages.form-insights';

    public ?array $data = [];

    private ?FormInsights $insights = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-pie';

    public static function getResource(): string
    {
        return FormBuilderPlugin::get()->getResource();
    }

    /** The form's Insights tab (next to the editor and its submissions). */
    public static function getNavigationLabel(): string
    {
        return __('admin_form_insights.action');
    }

    /** @param  array<string, mixed>  $parameters */
    public static function canAccess(array $parameters = []): bool
    {
        $resource = static::getResource();

        return isset($parameters['record'])
            ? $resource::canView($parameters['record'])
            : $resource::canViewAny();
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();

        $this->form->fill([
            'period' => 'all',
            'date_from' => null,
            'date_to' => null,
            'order_status' => null,
            'language' => app()->getLocale(),
        ]);
    }

    public function hydrate(): void
    {
        $this->authorizeAccess();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::canAccess(['record' => $this->getRecord()]), 403);
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin_form_insights.title', ['form' => $this->getRecordTitle()]);
    }

    public function getBreadcrumb(): string
    {
        return __('admin_form_insights.breadcrumb');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])
                    ->schema([
                        Select::make('period')
                            ->label(__('admin_form_insights.filters.period'))
                            ->options(collect(FormInsights::PERIODS)->mapWithKeys(fn (string $period) => [$period => __('admin_form_insights.periods.'.$period)])->all())
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live(),

                        DatePicker::make('date_from')
                            ->label(__('admin_form_insights.filters.date_from'))
                            ->visible(fn ($get) => $get('period') === 'custom')
                            ->live(),

                        DatePicker::make('date_to')
                            ->label(__('admin_form_insights.filters.date_to'))
                            ->visible(fn ($get) => $get('period') === 'custom')
                            ->live(),

                        Select::make('order_status')
                            ->label(__('admin_form_insights.filters.order_status'))
                            ->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $status) => [$status->value => $status->label()])->all())
                            ->placeholder(__('admin_form_insights.filters.all_statuses'))
                            ->helperText(__('admin_form_insights.filters.order_status_helper'))
                            ->visible(fn () => $this->insights()->isPaidForm())
                            ->native(false)
                            ->live(),

                        Select::make('language')
                            ->label(__('admin_form_insights.filters.language'))
                            ->options(collect(config('languages.available', []))->mapWithKeys(fn (array $meta, string $code) => [$code => $meta['name']])->all())
                            ->helperText(__('admin_form_insights.filters.language_helper'))
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->live(),
                    ]),
            ])
            ->statePath('data');
    }

    /** The insights for the filters as they stand (rebuilt on every request, the counting itself is cached). */
    public function insights(): FormInsights
    {
        /** @var Form $form */
        $form = $this->getRecord();

        return $this->insights ??= FormInsights::forPeriod(
            $form,
            (string) ($this->data['period'] ?? 'all'),
            $this->data['date_from'] ?? null,
            $this->data['date_to'] ?? null,
            filled($this->data['order_status'] ?? null) ? (string) $this->data['order_status'] : null,
        );
    }

    public function updatedData(): void
    {
        $this->insights = null;
    }

    /** The money of a paid form: only for those who may see the income report. */
    public function showsMoney(): bool
    {
        return $this->insights()->isPaidForm() && IncomeReport::canAccess();
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
                ->label(__('admin_form_insights.actions.pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(function () {
                    $export = app(FormInsightsExport::class);
                    $insights = $this->insights();

                    return Response::streamDownload(
                        fn () => print ($export->pdf($insights, $this->language(), $this->showsMoney())),
                        $export->filename($insights, 'pdf'),
                        ['Content-Type' => 'application/pdf'],
                    );
                }),

            Action::make('downloadCsv')
                ->label(__('admin_form_insights.actions.csv'))
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->action(function () {
                    $export = app(FormInsightsExport::class);
                    $insights = $this->insights();

                    return Response::streamDownload(
                        fn () => $export->csv(fopen('php://output', 'w'), $insights, $this->language(), $this->showsMoney()),
                        $export->filename($insights, 'csv'),
                        ['Content-Type' => 'text/csv; charset=UTF-8'],
                    );
                }),

            Action::make('edit')
                ->label(__('admin_form_insights.actions.edit_form'))
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->url(fn () => static::getResource()::getUrl('edit', ['record' => $this->getRecord()]))
                ->visible(fn () => static::getResource()::canEdit($this->getRecord())),
        ];
    }
}

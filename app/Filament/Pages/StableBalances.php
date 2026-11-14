<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StableResource;
use App\Models\BookingSlot;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableSettlement;
use App\Models\User;
use App\Services\Reports\ClosedBooks;
use App\Services\Reports\StableStatement;
use App\Services\Stables\StableSettlements;
use App\Support\Money;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

/**
 * Every stable's balance with us: what we owe the ones whose bookings we collected, what the ones
 * that collect themselves owe us. Payouts and receipts are recorded here once the money has moved.
 */
class StableBalances extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.stable-balances';

    /** @var array<int, int> balance by stable, worked out once per request */
    private array $balances = [];

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('admin_navigation.payments');
    }

    public static function getNavigationLabel(): string
    {
        return __('stable_statement.admin.title');
    }

    public function getSubheading(): ?string
    {
        $through = ClosedBooks::through();

        return $through ? __('closed_books.closed_through', ['date' => $through]) : null;
    }

    /** Closing a month (and reopening the last one) lives here, next to the payouts it protects. */
    protected function getHeaderActions(): array
    {
        $user = fn () => auth()->user() instanceof User ? auth()->user() : null;
        $run = function (callable $action, string $done): void {
            try {
                $action();
                Notification::make()->success()->title(__($done))->send();
            } catch (InvalidArgumentException $e) {
                Notification::make()->danger()->title($e->getMessage())->send();
            }
        };

        return [
            Action::make('closeMonth')
                ->label(__('closed_books.close'))
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->modalDescription(__('closed_books.close_hint'))
                ->schema([
                    Select::make('month')
                        ->label(__('closed_books.month'))
                        ->options(fn () => collect(range(1, 12))
                            ->map(fn (int $i) => CarbonImmutable::parse(BookingSlot::today())->startOfMonth()->subMonthsNoOverflow($i))
                            ->reject(fn (CarbonImmutable $m) => ClosedBooks::isClosed($m->endOfMonth()->toDateString()))
                            ->mapWithKeys(fn (CarbonImmutable $m) => [$m->format('Y-m') => $m->locale(app()->getLocale())->translatedFormat('F Y')]))
                        ->default(CarbonImmutable::parse(BookingSlot::today())->subMonthNoOverflow()->format('Y-m'))
                        ->required()
                        ->native(false),
                    Textarea::make('note')->label(__('closed_books.note'))->rows(2)->maxLength(500),
                ])
                ->action(fn (array $data) => $run(fn () => app(ClosedBooks::class)->close($data['month'], $user(), $data['note'] ?? null), 'closed_books.closed')),
            Action::make('reopenMonth')
                ->label(fn () => __('closed_books.reopen', ['month' => CarbonImmutable::parse((string) ClosedBooks::through())->locale(app()->getLocale())->translatedFormat('F Y')]))
                ->icon('heroicon-o-lock-open')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('closed_books.reopen_hint'))
                // super admins only: Shield lets them past every check, and nobody else holds this permission
                ->visible(fn () => ClosedBooks::through() !== null && (bool) $user()?->can('ReopenBooks'))
                ->action(fn () => $run(fn () => app(ClosedBooks::class)->reopen($user()), 'closed_books.reopened')),
        ];
    }

    public function getTitle(): string
    {
        return __('stable_statement.admin.title');
    }

    public function balanceOf(Stable $stable): int
    {
        return $this->balances[$stable->getKey()] ??= StableStatement::balance($stable);
    }

    /** @return array{we_owe: int, they_owe: int} over every stable with an account */
    public function totals(): array
    {
        $balances = $this->accountsQuery()->get()->map(fn (Stable $stable) => $this->balanceOf($stable));

        return [
            'we_owe' => (int) $balances->filter(fn (int $b) => $b > 0)->sum(),
            'they_owe' => (int) -$balances->filter(fn (int $b) => $b < 0)->sum(),
        ];
    }

    /** @return Builder<Stable> stables with bookings money or settlements */
    private function accountsQuery(): Builder
    {
        return Stable::query()->where(fn (Builder $q) => $q
            ->whereIn('id', ServiceOrder::query()->whereNotNull('stable_id')->select('stable_id'))
            ->orWhereHas('settlements'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->accountsQuery()->with('owners'))
            ->defaultSort('en_name')
            ->columns([
                TextColumn::make('en_name')
                    ->label(__('stable_statement.fields.stable'))
                    ->description(fn (Stable $record) => $record->owners->pluck('name')->implode(', '))
                    ->searchable(['en_name', 'ar_name'])
                    ->sortable()
                    ->url(fn (Stable $record) => StableResource::getUrl('view', ['record' => $record])),
                TextColumn::make('formatted_commission')
                    ->label(__('stable_panel.fields.commission'))
                    ->placeholder('—'),
                TextColumn::make('collection')
                    ->label(__('stable_statement.admin.collection'))
                    ->state(fn (Stable $record) => $record->activePaymentAccount()
                        ? __('stable_statement.admin.collects_own', ['gateway' => $record->activePaymentAccount()->gateway->label()])
                        : __('stable_statement.admin.collects_ours'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('balance')
                    ->label(__('stable_statement.admin.balance'))
                    ->state(fn (Stable $record) => $this->balanceOf($record))
                    ->formatStateUsing(fn (int $state) => new HtmlString(Money::formatHtml(abs($state))->toHtml().' · '.e(__('stable_statement.admin.'.($state > 0 ? 'we_owe' : ($state < 0 ? 'they_owe' : 'settled'))))))
                    ->color(fn (int $state) => $state > 0 ? 'success' : ($state < 0 ? 'warning' : 'gray'))
                    ->weight('bold'),
                TextColumn::make('last_settlement')
                    ->label(__('stable_statement.admin.last_settlement'))
                    ->state(fn (Stable $record) => $record->settlements()->latest('paid_on')->value('paid_on'))
                    ->date()
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('statement')
                    ->label(__('stable_statement.admin.statement'))
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->url(fn (Stable $record) => StableStatementReport::getUrl(['stable' => $record->getKey()])),
                ActionGroup::make([
                    $this->settlementAction(StableSettlement::PAYOUT),
                    $this->settlementAction(StableSettlement::RECEIPT),
                ])->label(__('stable_statement.admin.record'))->icon('heroicon-o-banknotes')->button()->color('primary'),
            ])
            ->emptyStateHeading(__('stable_statement.admin.empty'))
            ->emptyStateIcon('heroicon-o-scale');
    }

    public static function settlementForm(string $direction, int $suggested = 0): array
    {
        return [
            TextInput::make('amount')
                ->label(__('stable_statement.fields.amount'))
                ->numeric()
                ->minValue(0.001)
                ->step(0.001)
                ->default($suggested > 0 ? number_format($suggested / 1000, 3, '.', '') : null)
                ->required(),
            DatePicker::make('paid_on')
                ->label(__('stable_statement.fields.paid_on'))
                // Oman's day: the server's UTC date is still yesterday until 4 a.m.
                ->default(BookingSlot::today())
                ->minDate(ClosedBooks::firstOpenDay())
                ->maxDate(BookingSlot::today())
                ->required(),
            Select::make('method')
                ->label(__('stable_statement.fields.method'))
                ->options(collect(StableSettlement::METHODS)->mapWithKeys(fn (string $m) => [$m => __('stable_statement.methods.'.$m)]))
                ->default('bank_transfer')
                ->required()
                ->native(false),
            TextInput::make('reference')
                ->label(__('stable_statement.fields.reference'))
                ->maxLength(190),
            Textarea::make('note')
                ->label(__('stable_statement.fields.note'))
                ->rows(2)
                ->maxLength(1000),
        ];
    }

    public static function recordSettlement(Stable $stable, string $direction, array $data): void
    {
        $user = auth()->user();

        try {
            app(StableSettlements::class)->record(
                $stable, $direction, $data['amount'], substr((string) $data['paid_on'], 0, 10), $data['method'],
                $data['reference'] ?? null, $data['note'] ?? null, $user instanceof User ? $user : null,
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title(__('stable_statement.admin.recorded'))->send();
    }

    private function settlementAction(string $direction): Action
    {
        return Action::make($direction)
            ->label(__('stable_statement.admin.record_'.$direction))
            ->icon($direction === StableSettlement::PAYOUT ? 'heroicon-o-arrow-up-right' : 'heroicon-o-arrow-down-left')
            ->modalDescription(__('stable_statement.admin.record_'.$direction.'_hint'))
            ->visible(fn (Stable $record) => Gate::allows('update', $record))
            ->schema(fn (Stable $record) => static::settlementForm($direction, $direction === StableSettlement::PAYOUT ? max(0, StableStatement::payable($record)) : max(0, -$this->balanceOf($record))))
            ->action(function (Stable $record, array $data) use ($direction): void {
                static::recordSettlement($record, $direction, $data);
                unset($this->balances[$record->getKey()]);
            });
    }
}

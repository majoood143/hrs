<?php

namespace App\Filament\Pages;

use App\Filament\Resources\StableResource;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableSettlement;
use App\Models\User;
use App\Services\Reports\StableStatement;
use App\Services\Stables\StableSettlements;
use App\Support\Money;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
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
                ->default(now()->toDateString())
                ->maxDate(now())
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

        app(StableSettlements::class)->record(
            $stable, $direction, $data['amount'], substr((string) $data['paid_on'], 0, 10), $data['method'],
            $data['reference'] ?? null, $data['note'] ?? null, $user instanceof User ? $user : null,
        );

        Notification::make()->success()->title(__('stable_statement.admin.recorded'))->send();
    }

    private function settlementAction(string $direction): Action
    {
        return Action::make($direction)
            ->label(__('stable_statement.admin.record_'.$direction))
            ->icon($direction === StableSettlement::PAYOUT ? 'heroicon-o-arrow-up-right' : 'heroicon-o-arrow-down-left')
            ->modalDescription(__('stable_statement.admin.record_'.$direction.'_hint'))
            ->visible(fn (Stable $record) => Gate::allows('update', $record))
            ->schema(fn (Stable $record) => static::settlementForm($direction, $direction === StableSettlement::PAYOUT ? max(0, $this->balanceOf($record)) : max(0, -$this->balanceOf($record))))
            ->action(function (Stable $record, array $data) use ($direction): void {
                static::recordSettlement($record, $direction, $data);
                unset($this->balances[$record->getKey()]);
            });
    }
}

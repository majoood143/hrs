<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StableBookingStatus;
use App\Models\BookingSlot;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableSettlement;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A stable's account with us for a period: the bookings that carried money (dated the day they
 * were paid, or for pay-at-the-stable ones the day the rider was marked attended), the payouts and
 * receipts recorded, and the balance before and after. A positive balance is what we owe the
 * stable; a negative one what it owes us.
 *
 * Counted: online bookings paid (or since refunded), and pay-at-the-stable bookings once attended.
 * Not counted: free ones, unpaid ones, and pay-at-the-stable ones not attended (no money moved).
 */
class StableStatement
{
    /** @var Collection<int, StableLedgerRow>|null */
    private ?Collection $allRows = null;

    /** @var Collection<int, StableLedgerRow>|null */
    private ?Collection $periodRows = null;

    public function __construct(
        public readonly Stable $stable,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function forPeriod(Stable $stable, string $period, ?string $from = null, ?string $to = null): self
    {
        [$start, $end] = IncomeStatement::period($period, $from, $to);

        return new self($stable, $start, $end);
    }

    /** Everything up to now, for the stable's current balance. */
    public static function allTime(Stable $stable): self
    {
        return new self($stable, CarbonImmutable::create(2000, 1, 1, 0, 0, 0, BookingSlot::timezone()), CarbonImmutable::now(BookingSlot::timezone())->endOfDay());
    }

    /** @return Builder<ServiceOrder> the orders that carry money between a stable (null: every stable) and us */
    public static function ordersQuery(Stable|int|null $stable): Builder
    {
        $id = $stable instanceof Stable ? $stable->getKey() : $stable;

        return ServiceOrder::query()
            ->when($id !== null, fn (Builder $q) => $q->where('stable_id', $id), fn (Builder $q) => $q->whereNotNull('stable_id'))
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q
                    ->whereIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::Refunded->value])
                    ->whereNotNull('paid_at'))
                ->orWhere(fn (Builder $q) => $q
                    ->where('payment_status', PaymentStatus::OnSite->value)
                    ->whereHas('stableBooking', fn (Builder $b) => $b->where('status', StableBookingStatus::Completed->value))))
            ->with(['stableBooking.offering', 'stableBooking.slot', 'refunds']);
    }

    /**
     * Every row up to the end of the period, oldest first: only the opening balance needs them.
     * shortcut: loads the stable's whole history (the all-time balance too); fine for thousands of
     * orders per stable, keep a stored running balance once a stable has tens of thousands.
     *
     * @return Collection<int, StableLedgerRow>
     */
    private function rowsUpToEnd(): Collection
    {
        return $this->allRows ??= $this->ledger(static::ordersQuery($this->stable))
            ->filter(fn (StableLedgerRow $row) => $row->date->lte($this->to))
            ->values();
    }

    /** @return Collection<int, StableLedgerRow> the period's rows, oldest first */
    public function rows(): Collection
    {
        if ($this->allRows !== null) {
            return $this->allRows->filter(fn (StableLedgerRow $row) => $row->date->gte($this->from))->values();
        }

        // an entry's date is its sale's paid, attended, completed or created time, or a refund's: one of
        // them falls in the period, so the database narrows to those orders and the exact date decides
        $range = [$this->from->setTimezone(config('app.timezone')), $this->to->setTimezone(config('app.timezone'))];

        return $this->periodRows ??= $this->ledger(static::ordersQuery($this->stable)->where(fn (Builder $q) => $q
            ->whereBetween('paid_at', $range)
            ->orWhereBetween('completed_at', $range)
            ->orWhereBetween('created_at', $range)
            ->orWhereHas('stableBooking', fn (Builder $b) => $b->whereBetween('attended_at', $range))
            ->orWhereHas('refunds', fn (Builder $r) => $r->whereBetween('created_at', $range))))
            ->filter(fn (StableLedgerRow $row) => $row->date->betweenIncluded($this->from, $this->to))
            ->values();
    }

    /** @return Collection<int, StableLedgerRow> oldest first */
    private function ledger(Builder $orders): Collection
    {
        return $orders->get()
            ->flatMap(fn (ServiceOrder $order) => StableLedgerRow::entriesFor($order))
            ->sortBy(fn (StableLedgerRow $row) => $row->date->getTimestamp().'-'.str_pad((string) $row->orderId, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /** @return Collection<int, StableSettlement> */
    public function settlements(): Collection
    {
        return $this->stable->settlements()
            ->whereBetween('paid_on', [$this->from->toDateString(), $this->to->toDateString()])
            ->orderBy('paid_on')->orderBy('id')
            ->get();
    }

    /** The balance before the period starts. */
    public function opening(): int
    {
        $bookings = (int) $this->rowsUpToEnd()->filter(fn (StableLedgerRow $row) => $row->date->lt($this->from))->sum(fn (StableLedgerRow $row) => $row->net());

        $settled = (int) $this->stable->settlements()
            ->where('paid_on', '<', $this->from->toDateString())
            ->get()
            ->sum(fn (StableSettlement $s) => $s->effectOnBalance());

        return $bookings + $settled;
    }

    public function closing(): int
    {
        $t = $this->totals();

        return $this->opening() + $t['net'] - $t['payouts'] + $t['receipts'];
    }

    /**
     * @return array{bookings: int, riders: int, collected: int, collected_by_us: int, collected_by_stable: int, refunded: int,
     *               stable_share: int, fee: int, commission: int, vat_on_commission: int, our_share: int,
     *               owed_to_stable: int, owed_by_stable: int, net: int, payouts: int, receipts: int}
     */
    public function totals(): array
    {
        $rows = $this->rows();
        $settlements = $this->settlements();
        $sum = fn (callable $f) => (int) $rows->sum($f);

        return [
            'bookings' => $rows->reject(fn (StableLedgerRow $r) => $r->isRefund())->count(),
            'riders' => $sum(fn (StableLedgerRow $r) => $r->riders),
            'collected' => $sum(fn (StableLedgerRow $r) => $r->collected()),
            'collected_by_us' => $sum(fn (StableLedgerRow $r) => $r->heldByUs() ? $r->collected() : 0),
            'collected_by_stable' => $sum(fn (StableLedgerRow $r) => $r->heldByUs() ? 0 : $r->collected()),
            'refunded' => $sum(fn (StableLedgerRow $r) => $r->refunded),
            'stable_share' => $sum(fn (StableLedgerRow $r) => $r->stableShare()),
            'fee' => $sum(fn (StableLedgerRow $r) => $r->fee + $r->vatOnFee),
            'commission' => $sum(fn (StableLedgerRow $r) => $r->commission),
            'vat_on_commission' => $sum(fn (StableLedgerRow $r) => $r->vatOnCommission),
            'our_share' => $sum(fn (StableLedgerRow $r) => $r->ourShare()),
            'owed_to_stable' => $sum(fn (StableLedgerRow $r) => $r->owedToStable()),
            'owed_by_stable' => $sum(fn (StableLedgerRow $r) => $r->owedByStable()),
            'net' => $sum(fn (StableLedgerRow $r) => $r->net()),
            'payouts' => (int) $settlements->where('direction', StableSettlement::PAYOUT)->sum(fn (StableSettlement $s) => $s->amountBaisa()),
            'receipts' => (int) $settlements->where('direction', StableSettlement::RECEIPT)->sum(fn (StableSettlement $s) => $s->amountBaisa()),
        ];
    }

    /** The stable's balance now (positive: we owe it). */
    public static function balance(Stable $stable): int
    {
        return static::allTime($stable)->closing();
    }

    /**
     * The stable's share of bookings paid through us and then cancelled, with no refund recorded
     * yet: that money is going back to the customer, so it is held back from payouts meanwhile.
     */
    public static function heldForRefund(Stable $stable): int
    {
        return (int) static::ordersQuery($stable)
            ->where('collected_by', 'platform')
            ->where('payment_status', PaymentStatus::Paid->value)
            ->whereIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])
            ->where('refunded_amount', 0)
            ->get()
            ->sum(fn (ServiceOrder $order) => StableLedgerRow::fromOrder($order)->stableShare());
    }

    /** "We owe you this", plus what is held back for refunds when there is any, for the owner's balance. */
    public static function balanceLabel(Stable $stable, ?int $balance = null): string
    {
        $balance ??= static::balance($stable);
        $held = static::heldForRefund($stable);
        $label = __('stable_statement.widget.balance_'.($balance > 0 ? 'we_owe' : ($balance < 0 ? 'you_owe' : 'settled')));

        return $held > 0 ? $label.' · '.__('stable_statement.widget.held', ['amount' => Money::format($held)]) : $label;
    }

    /** What may be paid out to the stable now: the balance less what is held back for refunds (negative: it owes us). */
    public static function payable(Stable $stable): int
    {
        return static::balance($stable) - static::heldForRefund($stable);
    }

    /**
     * Past pay-at-the-stable sessions the stable has not marked yet: they count only once the rider
     * is marked attended, so the admins can see what is missing from a statement.
     */
    public static function unmarkedAtStable(Stable|int $stable): int
    {
        $id = $stable instanceof Stable ? $stable->getKey() : $stable;

        return ServiceOrder::query()
            ->where('stable_id', $id)
            ->where('payment_status', PaymentStatus::OnSite->value)
            ->whereHas('stableBooking', fn (Builder $b) => $b
                ->where('status', StableBookingStatus::Confirmed->value)
                ->whereHas('slot', fn (Builder $s) => $s->where('date', '<', BookingSlot::today())))
            ->count();
    }
}

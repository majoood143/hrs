<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Enums\StableBookingStatus;
use App\Models\BookingSlot;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Models\StableSettlement;
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
        return new self($stable, CarbonImmutable::create(2000), CarbonImmutable::now()->endOfDay());
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
            ->with(['stableBooking.offering', 'stableBooking.slot']);
    }

    /** @return Collection<int, StableLedgerRow> every row up to the end of the period, oldest first */
    private function rowsUpToEnd(): Collection
    {
        return $this->allRows ??= static::ordersQuery($this->stable)
            ->get()
            ->map(fn (ServiceOrder $order) => StableLedgerRow::fromOrder($order))
            ->filter(fn (StableLedgerRow $row) => $row->date->lte($this->to))
            ->sortBy(fn (StableLedgerRow $row) => $row->date->getTimestamp().'-'.str_pad((string) $row->orderId, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /** @return Collection<int, StableLedgerRow> */
    public function rows(): Collection
    {
        return $this->rowsUpToEnd()->filter(fn (StableLedgerRow $row) => $row->date->gte($this->from))->values();
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
            'bookings' => $rows->count(),
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

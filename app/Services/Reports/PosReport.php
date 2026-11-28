<?php

namespace App\Services\Reports;

use App\Enums\PaymentStatus;
use App\Models\BookingSlot;
use App\Models\PosPayment;
use App\Models\ServiceOrder;
use App\Models\Stable;
use App\Support\StablePos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A stable's desk sales (the POS) for a period, in baisa: what was sold, how it was paid, refunds,
 * our commission and the stable's share. The money comes from the same dated entries as the
 * statement (StableLedgerRow), so the two always agree: a sale counts the day it was paid, a refund
 * the day it was recorded. Payment links still unpaid are counted apart (no money moved yet).
 */
final class PosReport
{
    /** @var Collection<int, ServiceOrder>|null */
    private ?Collection $orders = null;

    /** @var Collection<int, StableLedgerRow>|null */
    private ?Collection $entries = null;

    public function __construct(
        public readonly Stable $stable,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function forPeriod(Stable $stable, string $period): self
    {
        [$from, $to] = IncomeStatement::period($period);

        return new self($stable, CarbonImmutable::instance($from), CarbonImmutable::instance($to));
    }

    /** @return Builder<ServiceOrder> */
    public static function deskOrders(Stable $stable): Builder
    {
        return ServiceOrder::query()
            ->where('stable_id', $stable->getKey())
            ->whereNotNull('desk_sold_by')
            ->with(['stableBooking.offering', 'stableBooking.slot', 'packagePurchase.package', 'posPayments', 'deskSeller', 'refunds']);
    }

    /** @return Collection<int, ServiceOrder> the desk orders made, paid or refunded in the period, newest first */
    public function orders(): Collection
    {
        $range = [$this->from, $this->to];

        return $this->orders ??= self::deskOrders($this->stable)
            ->where(fn (Builder $q) => $q
                ->whereBetween('created_at', $range)
                ->orWhereBetween('paid_at', $range)
                ->orWhereHas('refunds', fn (Builder $r) => $r->whereBetween('created_at', $range)))
            ->latest('id')
            ->get();
    }

    /** @return Collection<int, StableLedgerRow> the period's money: sales when paid, refunds when recorded */
    public function entries(): Collection
    {
        return $this->entries ??= $this->orders()
            ->filter(fn (ServiceOrder $o) => $o->paid_at !== null && in_array($o->payment_status, [PaymentStatus::Paid, PaymentStatus::Refunded], true))
            ->flatMap(fn (ServiceOrder $o) => StableLedgerRow::entriesFor($o))
            ->filter(fn (StableLedgerRow $e) => $e->date->betweenIncluded($this->from, $this->to))
            ->values();
    }

    /** @return array{sales: int, count: int, refunds: int, net: int, commission: int, stable_share: int, unpaid_links: int, unpaid_links_count: int} */
    public function totals(): array
    {
        $sales = $this->entries()->reject->isRefund();
        $refunds = $this->entries()->filter->isRefund();
        $unpaid = $this->orders()->filter(fn (ServiceOrder $o) => $o->isPayable());

        return [
            'sales' => (int) $sales->sum(fn (StableLedgerRow $e) => $e->total),
            'count' => $sales->count(),
            'refunds' => (int) $refunds->sum(fn (StableLedgerRow $e) => $e->refunded),
            'net' => (int) $this->entries()->sum(fn (StableLedgerRow $e) => $e->collected()),
            'commission' => (int) $this->entries()->sum(fn (StableLedgerRow $e) => $e->commission + $e->vatOnCommission),
            'stable_share' => (int) $this->entries()->sum(fn (StableLedgerRow $e) => $e->stableShare()),
            'unpaid_links' => (int) $unpaid->sum(fn (ServiceOrder $o) => $o->totalBaisa()),
            'unpaid_links_count' => $unpaid->count(),
        ];
    }

    /**
     * How the period's sales were paid: each desk method, and payment links paid online. Refunds are
     * not split by method (the stable gives the money back as it likes).
     *
     * @return list<array{method: string, total: int, count: int}>
     */
    public function byMethod(): array
    {
        $paid = $this->entries()->reject->isRefund()->pluck('orderId')->flip();
        $orders = $this->orders()->filter(fn (ServiceOrder $o) => $paid->has($o->getKey()));
        $rows = $orders->flatMap(fn (ServiceOrder $o) => $o->isDeskSale()
            ? $o->posPayments->map(fn (PosPayment $p) => ['method' => $p->method, 'total' => $p->amountBaisa()])
            : [['method' => StablePos::LINK, 'total' => $o->totalBaisa()]]);

        return $rows->groupBy('method')
            ->map(fn (Collection $g, string $method) => ['method' => $method, 'total' => (int) $g->sum('total'), 'count' => $g->count()])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** The service or the package sold (the stable's own name left out: it is its desk). */
    public static function itemName(ServiceOrder $order): string
    {
        return (string) ($order->stableBooking?->offering?->name ?? $order->packagePurchase?->package?->name ?? $order->packagePurchase?->name ?? $order->serviceName());
    }

    /** @return list<array{item: string, total: int, count: int}> sales by service or package, best first */
    public function byItem(): array
    {
        $items = $this->orders()->mapWithKeys(fn (ServiceOrder $o) => [$o->getKey() => self::itemName($o)]);

        return $this->entries()->reject->isRefund()
            ->groupBy(fn (StableLedgerRow $e) => $items[$e->orderId] ?? $e->service)
            ->map(fn (Collection $g, string $item) => ['item' => $item, 'total' => (int) $g->sum(fn (StableLedgerRow $e) => $e->total), 'count' => $g->count()])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** @return list<array{name: string, total: int, count: int}> sales by who made them at the desk */
    public function bySeller(): array
    {
        $sellers = $this->orders()->mapWithKeys(fn (ServiceOrder $o) => [$o->getKey() => (string) $o->deskSeller?->name]);

        return $this->entries()->reject->isRefund()
            ->groupBy(fn (StableLedgerRow $e) => $sellers[$e->orderId] ?? '')
            ->map(fn (Collection $g, string $name) => ['name' => $name, 'total' => (int) $g->sum(fn (StableLedgerRow $e) => $e->total), 'count' => $g->count()])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** @return list<array{date: string, total: int, count: int}> each day of the period (at most 366), Muscat days */
    public function byDay(): array
    {
        $sales = $this->entries()->reject->isRefund()->groupBy(fn (StableLedgerRow $e) => $e->date->toDateString());
        $days = [];
        $last = $this->to->setTimezone(BookingSlot::timezone())->toDateString();

        for ($day = $this->from->setTimezone(BookingSlot::timezone())->startOfDay(); $day->toDateString() <= $last && count($days) < 366; $day = $day->addDay()) {
            $group = $sales->get($day->toDateString(), collect());
            $days[] = ['date' => $day->toDateString(), 'total' => (int) $group->sum(fn (StableLedgerRow $e) => $e->total), 'count' => $group->count()];
        }

        return $days;
    }
}

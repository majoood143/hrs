<?php

namespace App\Services\Reports;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\BookingSlot;
use App\Models\Service;
use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The income of a period: every order PAID in it (by the day it was paid), what the customers paid,
 * what belongs to the client and what the client owes us (the service fees, the VAT on them, and the
 * commission). Free orders and unpaid ones carry no money and are left out. A refund is an entry of
 * its own on the day it was recorded (it reduces the client's share, and our fee only when that was
 * refundable), so a period already reported never changes.
 *
 * All the arithmetic is in baisa (whole numbers), so the columns of a statement always add up.
 */
class IncomeStatement
{
    public const PERIODS = ['today', 'this_week', 'this_month', 'last_month', 'this_year', 'custom'];

    /** @var Collection<int, IncomeRow>|null */
    private ?Collection $rows = null;

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly ?int $serviceId = null,
        public readonly ?string $gateway = null,
        public readonly ?int $formId = null,
    ) {}

    /** The first and last day of a named period ("custom" takes the two dates given). */
    public static function period(string $period, ?string $from = null, ?string $to = null, ?CarbonInterface $today = null): array
    {
        // days run midnight to midnight in Muscat, not UTC (a payment at 02:00 on the 1st is not last month's)
        $zone = BookingSlot::timezone();
        $today = CarbonImmutable::instance($today ?? now())->setTimezone($zone);

        [$start, $end] = match ($period) {
            'today' => [$today, $today],
            'this_week' => [$today->startOfWeek(), $today->endOfWeek()],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'custom' => [
                filled($from) ? CarbonImmutable::parse($from, $zone) : $today->startOfMonth(),
                filled($to) ? CarbonImmutable::parse($to, $zone) : $today,
            ],
            default => [$today->startOfMonth(), $today->endOfMonth()],
        };

        return $end->lt($start) ? [$end->startOfDay(), $start->endOfDay()] : [$start->startOfDay(), $end->endOfDay()];
    }

    public static function forPeriod(string $period, ?string $from = null, ?string $to = null, ?int $serviceId = null, ?string $gateway = null): self
    {
        [$start, $end] = static::period($period, $from, $to);

        return new self($start, $end, $serviceId, $gateway);
    }

    /** @return Collection<int, IncomeRow> payments and refunds, each on its own day, oldest first */
    public function rows(): Collection
    {
        // stored in the app's zone (UTC): a Muscat bound is written out as UTC, or the day shifts 4 hours
        $range = [$this->from->setTimezone(config('app.timezone')), $this->to->setTimezone(config('app.timezone'))];

        return $this->rows ??= ServiceOrder::query()
            ->whereIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::Refunded->value])
            ->whereNotNull('paid_at')
            // paid in the period, or refunded in it: each entry is dated on its own day below
            ->where(fn ($query) => $query->whereBetween('paid_at', $range)->orWhereHas('refunds', fn ($r) => $r->whereBetween('created_at', $range)))
            // money a stable took itself (its own gateway) never reached this account: phase 3's
            // stable statements cover what those stables owe us
            ->where(fn ($query) => $query->whereNull('collected_by')->orWhere('collected_by', '!=', 'stable'))
            ->when($this->serviceId, fn ($query, $id) => $query->where('service_id', $id))
            ->when($this->gateway, fn ($query, $gateway) => $query->where('payment_method', $gateway))
            ->when($this->formId, fn ($query, $id) => $query->where('form_id', $id))
            ->with('refunds')
            ->get()
            ->flatMap(fn (ServiceOrder $order) => IncomeRow::entriesFor($order))
            ->filter(fn (IncomeRow $row) => $row->paidAt->betweenIncluded($this->from, $this->to))
            ->sortBy(fn (IncomeRow $row) => $row->paidAt->getTimestamp().'-'.str_pad((string) $row->orderId, 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * Our fee and commission on bookings the stables collected themselves (their own gateway, or
     * paid at the stable): earned in the period, but owed to us by the stables (Stable balances),
     * so not in rows(). Zero when the report is narrowed to a service, gateway or form.
     * shortcut: loads every stable-collected order; keep a stored ledger once there are tens of thousands.
     */
    public function dueFromStables(): int
    {
        if ($this->serviceId || $this->gateway || $this->formId || ! ServiceOrder::query()->where('collected_by', 'stable')->exists()) {
            return 0;
        }

        return (int) StableStatement::ordersQuery(null)
            ->where('collected_by', 'stable')
            ->get()
            ->flatMap(fn (ServiceOrder $order) => StableLedgerRow::entriesFor($order))
            ->filter(fn (StableLedgerRow $row) => $row->date->betweenIncluded($this->from, $this->to))
            ->sum(fn (StableLedgerRow $row) => $row->ourShare());
    }

    /** The commission with its VAT: what the tables show in their "commission" column when VAT on commission is in use. */
    public static function commissionWithVat(array $group): int
    {
        return $group['commission'] + ($group['vat_on_commission'] ?? 0);
    }

    /** @return array<string, int> */
    public function totals(): array
    {
        return static::sum($this->rows());
    }

    /**
     * @param  Collection<int, IncomeRow>  $rows
     * @return array{orders: int, collected: int, price: int, vat_on_price: int, client_gross: int, fee: int, vat_on_fee: int, commission: int, refunded: int, due_to_us: int, gateway_cost: int, net_to_us: int, client_keeps: int}
     */
    public static function sum(Collection $rows): array
    {
        return [
            'orders' => $rows->reject(fn (IncomeRow $row) => $row->isRefund())->count(),
            'collected' => $rows->sum->total,
            'price' => $rows->sum->price,
            'vat_on_price' => $rows->sum->vatOnPrice,
            'client_gross' => $rows->sum(fn (IncomeRow $row) => $row->clientGross()),
            'fee' => $rows->sum->fee,
            'vat_on_fee' => $rows->sum->vatOnFee,
            'commission' => $rows->sum->commission,
            'vat_on_commission' => $rows->sum->vatOnCommission,
            'refunded' => $rows->sum->refunded,
            'due_to_us' => $rows->sum(fn (IncomeRow $row) => $row->dueToUs()),
            'gateway_cost' => $rows->sum->gatewayCost,
            'net_to_us' => $rows->sum(fn (IncomeRow $row) => $row->netToUs()),
            'client_keeps' => $rows->sum(fn (IncomeRow $row) => $row->clientKeeps()),
        ];
    }

    /**
     * The same figures per day (every day of the period, empty ones as zeros, so a chart has no gaps).
     *
     * @return Collection<string, array<string, int>> keyed by Y-m-d
     */
    public function byDay(): Collection
    {
        $days = collect();

        for ($day = $this->from->startOfDay(); $day->lte($this->to); $day = $day->addDay()) {
            $days->put($day->toDateString(), []);
        }

        $grouped = $this->rows()->groupBy(fn (IncomeRow $row) => $row->paidAt->toDateString());

        return $days->map(fn ($ignored, string $date) => static::sum($grouped->get($date, collect())));
    }

    /**
     * @return Collection<int, array{label: string, totals: array<string, int>}> biggest income first
     */
    public function byService(): Collection
    {
        $names = Service::query()->whereIn('id', $this->rows()->pluck('serviceId')->filter()->unique())->get()->keyBy('id');

        return $this->rows()
            ->groupBy(fn (IncomeRow $row) => $row->serviceId ?? 0)
            ->map(fn (Collection $rows, int $id) => [
                'label' => $id === 0 ? '—' : ($names->get($id)?->localizedName() ?? "#{$id}"),
                'totals' => static::sum($rows),
            ])
            ->sortByDesc(fn (array $group) => $group['totals']['due_to_us'])
            ->values();
    }

    /**
     * @return Collection<int, array{label: string, totals: array<string, int>}>
     */
    public function byGateway(): Collection
    {
        return $this->rows()
            ->groupBy(fn (IncomeRow $row) => $row->gateway ?? '')
            ->map(fn (Collection $rows, string $gateway) => [
                'label' => PaymentGateway::tryFrom($gateway)?->label() ?? '—',
                'totals' => static::sum($rows),
            ])
            ->sortByDesc(fn (array $group) => $group['totals']['collected'])
            ->values();
    }
}

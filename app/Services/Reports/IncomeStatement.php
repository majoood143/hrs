<?php

namespace App\Services\Reports;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Service;
use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The income of a period: every order PAID in it (by the day it was paid), what the customers paid,
 * what belongs to the client and what the client owes us (the service fees, the VAT on them, and the
 * commission). Free orders and unpaid ones carry no money and are left out. Refunded orders stay in:
 * the refund reduces the client's share, while our fee is kept.
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
    ) {}

    /** The first and last day of a named period ("custom" takes the two dates given). */
    public static function period(string $period, ?string $from = null, ?string $to = null, ?CarbonInterface $today = null): array
    {
        $today = CarbonImmutable::instance($today ?? now());

        [$start, $end] = match ($period) {
            'today' => [$today, $today],
            'this_week' => [$today->startOfWeek(), $today->endOfWeek()],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$today->startOfYear(), $today->endOfYear()],
            'custom' => [
                filled($from) ? CarbonImmutable::parse($from) : $today->startOfMonth(),
                filled($to) ? CarbonImmutable::parse($to) : $today,
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

    /** @return Collection<int, IncomeRow> */
    public function rows(): Collection
    {
        return $this->rows ??= ServiceOrder::query()
            ->whereIn('payment_status', [PaymentStatus::Paid->value, PaymentStatus::Refunded->value])
            ->whereBetween('paid_at', [$this->from, $this->to])
            ->when($this->serviceId, fn ($query, $id) => $query->where('service_id', $id))
            ->when($this->gateway, fn ($query, $gateway) => $query->where('payment_method', $gateway))
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get()
            ->map(fn (ServiceOrder $order) => IncomeRow::fromOrder($order));
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
     * @return array{orders: int, collected: int, price: int, vat_on_price: int, client_gross: int, fee: int, vat_on_fee: int, commission: int, refunded: int, due_to_us: int, client_keeps: int}
     */
    public static function sum(Collection $rows): array
    {
        return [
            'orders' => $rows->count(),
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

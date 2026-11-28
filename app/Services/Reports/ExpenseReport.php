<?php

namespace App\Services\Reports;

use App\Models\Expense;
use App\Models\Stable;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The expenses of one stable, or ours ($stable null), dated in a period (the same named periods as
 * the income reports, Muscat days), summed in baisa. Empty while the Expenses module is off.
 */
final class ExpenseReport
{
    /** @var Collection<int, Expense>|null */
    private ?Collection $rows = null;

    public function __construct(
        public readonly ?Stable $stable,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    public static function forPeriod(?Stable $stable, string $period, ?string $from = null, ?string $to = null): self
    {
        [$start, $end] = IncomeStatement::period($period, $from, $to);

        return new self($stable, $start, $end);
    }

    /** @return Collection<int, Expense> newest first */
    public function rows(): Collection
    {
        if (! Expense::enabled()) {
            return collect();
        }

        return $this->rows ??= Expense::query()
            ->when($this->stable, fn ($q) => $q->where('stable_id', $this->stable->getKey()), fn ($q) => $q->platform())
            ->whereBetween('spent_on', [$this->from->toDateString(), $this->to->toDateString()])
            ->with(['category', 'horse'])
            ->orderByDesc('spent_on')->orderByDesc('id')
            ->get();
    }

    public function total(): int
    {
        return (int) $this->rows()->sum(fn (Expense $e) => $e->baisa());
    }

    /** @return Collection<int, array{id: int, label: string, total: int, count: int}> biggest first */
    public function byCategory(): Collection
    {
        return $this->rows()
            ->groupBy('expense_category_id')
            ->map(fn (Collection $group, $id) => [
                'id' => (int) $id,
                'label' => (string) $group->first()->category?->name,
                'total' => (int) $group->sum(fn (Expense $e) => $e->baisa()),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values();
    }
}

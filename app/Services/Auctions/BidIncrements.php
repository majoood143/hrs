<?php

namespace App\Services\Auctions;

use InvalidArgumentException;

/** An auction's bid steps: from a price upwards, each bid must add at least `step` (all in baisa). */
final class BidIncrements
{
    /** @var array<int, array{from: int, step: int}> highest `from` first */
    private array $rows;

    /** @param  array<int, array{from: mixed, step: mixed}>  $rows  in OMR, as stored on the auction */
    public function __construct(array $rows)
    {
        $this->rows = collect($rows)
            ->map(fn (array $row) => ['from' => Money::baisa($row['from'] ?? 0), 'step' => Money::baisa($row['step'] ?? 0)])
            ->filter(fn (array $row) => $row['step'] > 0)
            ->sortByDesc('from')
            ->values()
            ->all();

        if ($this->rows === []) {
            throw new InvalidArgumentException('An auction needs at least one bid step.');
        }
    }

    /** The step that applies once the price is `$price`. */
    public function after(int $price): int
    {
        foreach ($this->rows as $row) {
            if ($price >= $row['from']) {
                return $row['step'];
            }
        }

        return end($this->rows)['step'];
    }
}

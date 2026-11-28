<?php

namespace Tests\Unit;

use App\Services\Auctions\BidIncrements;
use App\Services\Auctions\ProxyBidding;
use PHPUnit\Framework\TestCase;

/** The auction rules without a database. Amounts in baisa; steps: 100 OMR below 5,000, then 250. */
class ProxyBiddingTest extends TestCase
{
    private const EMPTY = ['price' => null, 'leader' => null, 'leader_max' => null];

    private BidIncrements $steps;

    protected function setUp(): void
    {
        $this->steps = new BidIncrements([['from' => 5000, 'step' => 250], ['from' => 0, 'step' => 100]]);
    }

    private static function omr(int $omr): int
    {
        return $omr * 1000;
    }

    public function test_steps_follow_the_table_and_the_first_bid_is_the_starting_price(): void
    {
        $this->assertSame(self::omr(100), $this->steps->after(self::omr(4999)));
        $this->assertSame(self::omr(250), $this->steps->after(self::omr(5000)));
        $this->assertSame(self::omr(1000), ProxyBidding::minimumNext(self::EMPTY, self::omr(1000), $this->steps));
        $this->assertSame(self::omr(5250), ProxyBidding::minimumNext(['price' => self::omr(5000), 'leader' => 1, 'leader_max' => self::omr(5000)], self::omr(1000), $this->steps));
    }

    public function test_a_first_bid_with_a_maximum_leads_at_its_amount(): void
    {
        $r = ProxyBidding::apply(self::EMPTY, 1, self::omr(1000), self::omr(3000), null, $this->steps);

        $this->assertSame([self::omr(1000), 1, self::omr(3000)], [$r['price'], $r['leader'], $r['leader_max']]);
        $this->assertCount(1, $r['rows']);
    }

    public function test_the_leaders_proxy_answers_one_step_above_a_lower_bid(): void
    {
        $state = ['price' => self::omr(1000), 'leader' => 1, 'leader_max' => self::omr(3000)];
        $r = ProxyBidding::apply($state, 2, self::omr(1500), null, null, $this->steps);

        $this->assertSame([self::omr(1600), 1], [$r['price'], $r['leader']]);
        $this->assertSame([[2, 'bid', self::omr(1500)], [1, 'auto', self::omr(1600)]], self::shape($r));
    }

    public function test_a_higher_maximum_takes_the_lead_one_step_above_the_old_maximum(): void
    {
        $state = ['price' => self::omr(1000), 'leader' => 1, 'leader_max' => self::omr(3000)];
        $r = ProxyBidding::apply($state, 2, self::omr(1100), self::omr(8000), null, $this->steps);

        $this->assertSame([self::omr(3100), 2, self::omr(8000)], [$r['price'], $r['leader'], $r['leader_max']]);
        $this->assertSame([[2, 'bid', self::omr(1100)], [1, 'auto', self::omr(3000)], [2, 'auto', self::omr(3100)]], self::shape($r));
    }

    public function test_the_price_never_goes_above_the_new_maximum(): void
    {
        $state = ['price' => self::omr(1000), 'leader' => 1, 'leader_max' => self::omr(3000)];
        $r = ProxyBidding::apply($state, 2, self::omr(1100), self::omr(3050), null, $this->steps);

        $this->assertSame([self::omr(3050), 2], [$r['price'], $r['leader']]);
    }

    public function test_equal_maximums_keep_the_earlier_bidder(): void
    {
        $state = ['price' => self::omr(1000), 'leader' => 1, 'leader_max' => self::omr(3000)];
        $r = ProxyBidding::apply($state, 2, self::omr(3000), null, null, $this->steps);

        $this->assertSame([self::omr(3000), 1], [$r['price'], $r['leader']]);
    }

    public function test_a_direct_bid_above_the_leaders_maximum_wins_at_its_amount(): void
    {
        $state = ['price' => self::omr(1000), 'leader' => 1, 'leader_max' => self::omr(1000)];
        $r = ProxyBidding::apply($state, 2, self::omr(2000), null, null, $this->steps);

        $this->assertSame([self::omr(2000), 2], [$r['price'], $r['leader']]);
        $this->assertSame([[2, 'bid', self::omr(2000)]], self::shape($r));
    }

    public function test_the_leader_only_raises_their_maximum(): void
    {
        $state = ['price' => self::omr(1000), 'leader' => 1, 'leader_max' => self::omr(1000)];
        $r = ProxyBidding::apply($state, 1, self::omr(1100), self::omr(5000), null, $this->steps);

        $this->assertSame([self::omr(1000), 1, self::omr(5000)], [$r['price'], $r['leader'], $r['leader_max']]);
        $this->assertSame([[1, 'max', self::omr(1000)]], self::shape($r));
    }

    public function test_a_maximum_that_covers_the_reserve_jumps_the_price_to_it(): void
    {
        $r = ProxyBidding::apply(self::EMPTY, 1, self::omr(1000), self::omr(6000), self::omr(4000), $this->steps);
        $this->assertSame(self::omr(4000), $r['price']);

        $low = ProxyBidding::apply(self::EMPTY, 1, self::omr(1000), self::omr(3000), self::omr(4000), $this->steps);
        $this->assertSame(self::omr(1000), $low['price']);
    }

    /** @return array<int, array{0: int, 1: string, 2: int}> */
    private static function shape(array $result): array
    {
        return array_map(fn (array $row) => [$row['bidder'], $row['kind'], $row['amount']], $result['rows']);
    }
}

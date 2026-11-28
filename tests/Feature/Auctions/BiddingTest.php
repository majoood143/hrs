<?php

namespace Tests\Feature\Auctions;

use App\Models\AuctionBid;
use App\Models\User;
use App\Services\Auctions\AuctionBidException;
use App\Services\Auctions\Bidding;
use App\Services\Auctions\CloseLot;
use Tests\Concerns\MakesAuctions;
use Tests\Concerns\PreparesCustomerSite;
use Tests\TestCase;

/** Bids through the database: who may bid, the lock-protected price, extensions, voids and the close. */
class BiddingTest extends TestCase
{
    use MakesAuctions;
    use PreparesCustomerSite;

    private Bidding $bidding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareCustomerSite();
        $this->prepareAuctions();
        $this->bidding = app(Bidding::class);
        $this->travelTo($this->opensAt->copy()->addHour());
    }

    public function test_bids_follow_the_proxy_rules_and_keep_the_lot_up_to_date(): void
    {
        $lot = $this->lot(['starting_price' => 1000]);
        [$a, $b] = [$this->bidder(), $this->bidder()];

        $this->bidding->place($lot, $a, 1_000_000, 3_000_000);
        $lot = $this->bidding->place($lot, $b, 1_500_000);

        $this->assertSame('1600.000', $lot->current_price);
        $this->assertSame($a->id, $lot->leadingBid->auction_bidder_id);
        $this->assertSame(3, $lot->bids_count);
        $this->assertSame(2, $lot->bidders_count);
        $this->assertSame(['1000.000', '1500.000', '1600.000'], $lot->bids()->visible()->orderBy('id')->pluck('amount')->all());
    }

    public function test_who_may_bid_and_how_much(): void
    {
        $lot = $this->lot(['starting_price' => 1000, 'seller_customer_id' => null]);
        $approved = $this->bidder();

        $this->assertRefused('not_approved', fn () => $this->bidding->place($lot, $this->bidder(['status' => 'pending']), 1_000_000));
        $this->assertRefused('not_approved', fn () => $this->bidding->place($lot, $this->bidder([], $this->auction(['slug' => 'other'])), 1_000_000));
        $this->assertRefused('too_low', fn () => $this->bidding->place($lot, $approved, 999_000));
        $this->assertRefused('too_high', fn () => $this->bidding->place($lot, $approved, 10_001_000));
        $this->assertRefused('max_below_amount', fn () => $this->bidding->place($lot, $approved, 2_000_000, 1_500_000));

        $seller = $this->bidder();
        $lot->update(['seller_customer_id' => $seller->customer_id]);
        $this->assertRefused('own_lot', fn () => $this->bidding->place($lot, $seller, 1_000_000));
    }

    public function test_a_lot_takes_bids_only_in_its_window(): void
    {
        $lot = $this->lot();
        $bidder = $this->bidder();

        $this->travelTo($this->opensAt->copy()->subMinute());
        $this->assertRefused('not_open_yet', fn () => $this->bidding->place($lot, $bidder, 1_000_000));

        $this->travelTo($lot->ends_at);
        $this->assertRefused('closed', fn () => $this->bidding->place($lot, $bidder, 1_000_000));

        $lot->update(['status' => 'withdrawn']);
        $this->travelTo($this->opensAt->copy()->addHour());
        $this->assertRefused('closed', fn () => $this->bidding->place($lot, $bidder, 1_000_000));
    }

    public function test_a_bid_in_the_last_minutes_extends_only_that_lot(): void
    {
        $first = $this->lot(['lot_number' => 1]);
        $second = $this->lot(['lot_number' => 2]);
        $secondEnds = $second->ends_at->copy();
        $this->assertTrue($secondEnds->equalTo($first->ends_at->copy()->addSeconds(120)));

        $this->travelTo($first->ends_at->copy()->subMinute());
        $first = $this->bidding->place($first, $this->bidder(), 1_000_000);

        $this->assertTrue($first->ends_at->equalTo(now()->addMinutes(3)));
        $this->assertTrue($second->fresh()->ends_at->equalTo($secondEnds));

        // an early bid changes nothing
        $this->travelTo($this->opensAt->copy()->addHour());
        $before = $second->fresh()->ends_at;
        $this->assertTrue($this->bidding->place($second, $this->bidder(), 1_000_000)->ends_at->equalTo($before));
    }

    public function test_voiding_a_bid_works_the_lot_out_again(): void
    {
        $lot = $this->lot(['starting_price' => 1000]);
        [$a, $b, $c] = [$this->bidder(), $this->bidder(), $this->bidder()];

        $this->bidding->place($lot, $a, 1_000_000, 2_000_000);
        $this->bidding->place($lot, $b, 1_100_000, 9_000_000); // a typo-sized maximum
        $this->bidding->place($lot, $c, 2_200_000);
        $this->assertSame('2300.000', $lot->fresh()->current_price);

        $typo = AuctionBid::query()->where('auction_bidder_id', $b->id)->where('kind', 'bid')->firstOrFail();
        $lot = $this->bidding->void($typo, (new User)->forceFill(['id' => 1]), 'Typo, confirmed by phone');

        $this->assertSame('2200.000', $lot->current_price);
        $this->assertSame($c->id, $lot->leadingBid->auction_bidder_id);
        $this->assertNotNull($typo->fresh()->voided_at);
        $this->assertFalse($lot->bids()->visible()->where('auction_bidder_id', $b->id)->exists());
    }

    public function test_the_close_sells_to_the_leader_only_when_the_reserve_is_met(): void
    {
        $sold = $this->lot(['lot_number' => 1, 'reserve_price' => 1500]);
        $unsold = $this->lot(['lot_number' => 2, 'reserve_price' => 5000]);
        $empty = $this->lot(['lot_number' => 3]);
        $winner = $this->bidder();

        $this->bidding->place($sold, $winner, 1_000_000, 2_000_000);
        $this->bidding->place($unsold, $this->bidder(), 1_000_000);
        $this->assertSame('1500.000', $sold->fresh()->current_price); // the maximum covered the reserve

        $this->travelTo($empty->fresh()->ends_at);
        $this->assertSame(3, app(CloseLot::class)->due());
        $this->assertSame(0, app(CloseLot::class)->due());

        $sold = $sold->fresh();
        $this->assertSame(['sold', '1500.000', $winner->id], [$sold->status, $sold->hammer_price, $sold->winner_bidder_id]);
        $this->assertSame(['unsold', null], [$unsold->fresh()->status, $unsold->fresh()->hammer_price]);
        $this->assertSame('unsold', $empty->fresh()->status);
        $this->artisan('auctions:close-due')->assertSuccessful();
    }

    private function assertRefused(string $reason, callable $bid): void
    {
        try {
            $bid();
            $this->fail("Expected the bid to be refused: {$reason}");
        } catch (AuctionBidException $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertNotSame('auctions.errors.'.$reason, $e->getMessage());
        }
    }
}

<?php

namespace Tests\Concerns;

use App\Models\Auction;
use App\Models\AuctionBidder;
use App\Models\AuctionLot;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/** The customers and auction tables (real migrations) and an auction that opens for bids at `opensAt`. */
trait MakesAuctions
{
    protected Carbon $opensAt;

    protected ?Auction $defaultAuction = null;

    protected function prepareAuctions(): void
    {
        // a test may have built the customers (and orders) already: PreparesCustomerSite
        if (! Schema::hasTable('customers')) {
            (require database_path('migrations/2026_09_23_000001_create_customers_tables.php'))->up();
        }
        (require database_path('migrations/2026_11_24_000001_create_auctions_tables.php'))->up();
        (require database_path('migrations/2026_11_25_000001_add_auction_website_tables.php'))->up();
        (require database_path('migrations/2026_11_26_000001_add_auction_settlement.php'))->up();
        (require database_path('migrations/2026_11_27_000001_create_auction_applications_table.php'))->up();

        $this->opensAt = Carbon::parse('2026-12-01 18:00:00');
    }

    /** @param  array<string, mixed>  $attributes */
    protected function auction(array $attributes = []): Auction
    {
        return Auction::query()->create($attributes + [
            'slug' => 'winter-sale',
            'en_title' => 'Winter sale',
            'ar_title' => 'مزاد الشتاء',
            'status' => 'published',
            'preview_at' => $this->opensAt->copy()->subDays(3),
            'bidding_starts_at' => $this->opensAt,
            'ends_at' => $this->opensAt->copy()->addDays(2),
            'increments' => [['from' => 0, 'step' => 100], ['from' => 5000, 'step' => 250]],
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    protected function lot(array $attributes = []): AuctionLot
    {
        $auction = $this->defaultAuction ??= $this->auction();

        return AuctionLot::query()->create($attributes + [
            'auction_id' => $auction->id,
            'lot_number' => (int) $auction->lots()->max('lot_number') + 1,
            'en_name' => 'Bahar',
            'ar_name' => 'بحر',
            'starting_price' => 1000,
            'status' => 'ready',
        ])->fresh();
    }

    /** @param  array<string, mixed>  $attributes */
    protected function bidder(array $attributes = [], ?Auction $auction = null): AuctionBidder
    {
        $auction ??= $this->defaultAuction ??= $this->auction();
        static $phone = 96890000000;

        $customer = Customer::query()->create(['phone' => (string) $phone++, 'name' => 'Bidder']);

        return AuctionBidder::query()->create($attributes + [
            'auction_id' => $auction->id,
            'customer_id' => $customer->id,
            'status' => 'approved',
        ]);
    }
}

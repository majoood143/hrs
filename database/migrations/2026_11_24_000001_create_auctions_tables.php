<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online horse auctions (docs/horse-auctions-plan.md): an auction holds lots that close one after
 * another; approved bidders bid on them (proxy bids, anti-sniping extension). Money is OMR with 3
 * decimals, computed in baisa (Services\Auctions\Money).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('en_title');
            $table->string('ar_title');
            $table->text('en_description')->nullable();
            $table->text('ar_description')->nullable();
            $table->longText('en_conditions')->nullable();
            $table->longText('ar_conditions')->nullable();
            $table->string('cover_photo')->nullable();
            /** Auction::STATUSES */
            $table->string('status')->default('draft');
            /** The catalogue goes online (browse, register), then bidding opens, then the lots close one by one. */
            $table->timestamp('preview_at')->nullable();
            $table->timestamp('bidding_starts_at')->nullable();
            /** When the first lot closes; lot n closes (n - 1) × lot_gap_seconds later. */
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('lot_gap_seconds')->default(120);
            /** A bid in the last N minutes pushes that lot's close to N minutes from the bid. */
            $table->unsignedTinyInteger('extension_minutes')->default(3);
            /** [{from, step}] in OMR; empty = Auction::DEFAULT_INCREMENTS */
            $table->json('increments')->nullable();
            $table->decimal('deposit_amount', 10, 3)->default(0);
            $table->decimal('buyer_premium_percent', 5, 2)->default(0);
            $table->decimal('seller_commission_percent', 5, 2)->default(0);
            $table->decimal('entry_fee', 10, 3)->default(0);
            $table->unsignedTinyInteger('payment_days')->default(3);
            $table->timestamps();

            $table->index(['status', 'bidding_starts_at']);
        });

        Schema::create('auction_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('lot_number');
            $table->string('en_name');
            $table->string('ar_name');
            $table->string('en_tagline')->nullable();
            $table->string('ar_tagline')->nullable();

            /** AuctionLot::GENDERS */
            $table->string('gender')->nullable();
            $table->date('born_on')->nullable();
            $table->string('ueln')->nullable();
            $table->string('microchip')->nullable();
            $table->string('breed')->nullable();
            /** Stallion::COLOURS */
            $table->string('colour')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->string('genetic_tests')->nullable();
            $table->string('owner_name')->nullable();
            $table->string('owner_town')->nullable();
            $table->string('breeder_name')->nullable();
            $table->string('breeder_town')->nullable();
            $table->string('clinic')->nullable();
            /** VAT on this lot's hammer price (the seller's status decides it); null = none. */
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->unsignedBigInteger('racing_horse_id')->nullable();

            $table->string('sire_en')->nullable();
            $table->string('sire_ar')->nullable();
            $table->string('dam_en')->nullable();
            $table->string('dam_ar')->nullable();
            /** AuctionLot::PEDIGREE_KEYS => "Name", 4 generations behind the horse (Latin). */
            $table->json('pedigree')->nullable();
            $table->text('en_dam_line')->nullable();
            $table->text('ar_dam_line')->nullable();
            $table->text('en_note')->nullable();
            $table->text('ar_note')->nullable();

            $table->json('photos')->nullable();
            /** YouTube links */
            $table->json('videos')->nullable();
            /** Private files (vet report, X-rays) on the local disk, shown to approved bidders only. */
            $table->json('documents')->nullable();

            $table->foreignId('seller_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->decimal('starting_price', 10, 3);
            /** Never shown; the lot only says whether it is met. */
            $table->decimal('reserve_price', 10, 3)->nullable();
            $table->boolean('hide_result_price')->default(false);

            /** AuctionLot::STATUSES */
            $table->string('status')->default('draft');
            $table->timestamp('ends_at')->nullable();
            $table->decimal('current_price', 10, 3)->nullable();
            $table->unsignedBigInteger('leading_bid_id')->nullable();
            $table->unsignedInteger('bids_count')->default(0);
            $table->unsignedInteger('bidders_count')->default(0);
            $table->decimal('hammer_price', 10, 3)->nullable();
            $table->unsignedBigInteger('winner_bidder_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();

            $table->unique(['auction_id', 'lot_number']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('auction_bidders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            /** Shown instead of names ("Bidder 12"). */
            $table->unsignedInteger('paddle_number');
            /** AuctionBidder::STATUSES */
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('accepted_terms_at')->nullable();
            $table->string('terms_ip', 45)->nullable();
            $table->unsignedBigInteger('deposit_order_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['auction_id', 'customer_id']);
            $table->unique(['auction_id', 'paddle_number']);
        });

        Schema::create('auction_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_bidder_id')->constrained()->cascadeOnDelete();
            /** AuctionBid::KINDS: bid (placed), max (a leader raising their maximum), auto (placed by a proxy) */
            $table->string('kind', 8);
            $table->decimal('amount', 10, 3);
            /** The bidder's maximum at that moment; never shown to others. */
            $table->decimal('max_amount', 10, 3)->nullable();
            /** An admin who placed it for a bidder on the phone. */
            $table->unsignedBigInteger('placed_by_user_id')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by_user_id')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['auction_lot_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_bids');
        Schema::dropIfExists('auction_bidders');
        Schema::dropIfExists('auction_lots');
        Schema::dropIfExists('auctions');
    }
};

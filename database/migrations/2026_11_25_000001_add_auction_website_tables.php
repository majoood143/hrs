<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Auctions on the website (phase 2): organisers (an auction without one is run by the site itself),
 * auto-approval of bidders, the bidder's language for their messages, and watchlists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_organizers', function (Blueprint $table) {
            $table->id();
            $table->string('en_name');
            $table->string('ar_name');
            $table->string('logo')->nullable();
            /** The header colour of its auctions (#rrggbb). */
            $table->string('colour', 7)->nullable();
            $table->text('en_about')->nullable();
            $table->text('ar_about')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            /** [{name, en_role, ar_role, phone}] */
            $table->json('team')->nullable();
            $table->timestamps();
        });

        Schema::table('auctions', function (Blueprint $table) {
            $table->foreignId('organizer_id')->nullable()->after('id')->constrained('auction_organizers')->nullOnDelete();
            /** Approve a registration as soon as its conditions are accepted and its deposit (if any) is paid. */
            $table->boolean('auto_approve')->default(false)->after('deposit_amount');
        });

        Schema::table('auction_bidders', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('status');
            $table->index('deposit_order_id');
        });

        // a bidding deposit's order points at its registration (phase 3 adds the winner's invoice)
        if (Schema::hasTable('service_orders')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('auction_bidder_id')->nullable()->index();
            });
        }

        Schema::create('auction_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_lot_id')->constrained()->cascadeOnDelete();
            /** The "ends in 15 minutes" SMS went out. */
            $table->timestamp('ending_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'auction_lot_id']);
        });

        if (Schema::hasTable('roles') && Schema::hasTable('permissions')) {
            $role = Role::findOrCreate('auction_manager', 'web');
            foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $ability) {
                $role->givePermissionTo(Permission::findOrCreate("{$ability}:AuctionOrganizer", 'web'));
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_watches');
        if (Schema::hasColumn('service_orders', 'auction_bidder_id')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->dropIndex(['auction_bidder_id']);
                $table->dropColumn('auction_bidder_id');
            });
        }
        Schema::table('auction_bidders', function (Blueprint $table) {
            $table->dropIndex(['deposit_order_id']);
            $table->dropColumn('locale');
        });
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organizer_id');
            $table->dropColumn('auto_approve');
        });
        Schema::dropIfExists('auction_organizers');
    }
};

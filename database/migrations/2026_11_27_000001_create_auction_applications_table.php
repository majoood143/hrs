<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sellers applying to put a horse in an auction (phase 4): a signed-in customer sends the horse's
 * details, photos and vet documents from /auctions/sell; an admin approves it into a draft lot of an
 * auction (copying the details) or rejects it with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auction_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            /** AuctionApplication::STATUSES */
            $table->string('status')->default('pending');
            $table->string('locale', 5)->default('en');

            $table->string('horse_name');
            /** AuctionLot::GENDERS */
            $table->string('gender');
            $table->date('born_on')->nullable();
            $table->string('breed')->nullable();
            /** Stallion::COLOURS */
            $table->string('colour')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->string('sire')->nullable();
            $table->string('dam')->nullable();
            $table->string('ueln')->nullable();
            $table->string('microchip')->nullable();
            $table->string('owner_name');
            $table->string('owner_town')->nullable();
            $table->string('breeder_name')->nullable();
            /** What the seller would like as the reserve; the admin sets the real one. */
            $table->decimal('asking_reserve', 10, 3)->nullable();
            $table->text('notes')->nullable();
            $table->json('photos')->nullable();
            $table->json('videos')->nullable();
            /** private (local disk), like a lot's documents */
            $table->json('documents')->nullable();
            $table->timestamp('declared_at')->nullable();

            $table->text('reject_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->foreignId('auction_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        if (Schema::hasTable('roles') && Schema::hasTable('permissions')) {
            $role = Role::findOrCreate('auction_manager', 'web');
            foreach (['ViewAny', 'View', 'Update', 'Delete'] as $ability) {
                $role->givePermissionTo(Permission::findOrCreate("{$ability}:AuctionApplication", 'web'));
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_applications');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The stable desk (POS): an admin switches it on per stable, and each desk sale's payments (one row
 * per method when the customer splits it) are kept beside its order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stables', function (Blueprint $table) {
            $table->boolean('pos_enabled')->default(false)->after('booking_settings');
        });

        // who made the sale at the desk (paid there or sent a payment link): marks the order as a desk sale
        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('desk_sold_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('method', 30);
            $table->decimal('amount', 10, 3);
            $table->string('reference')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['method', 'created_at']);
        });

        // switches copied from the booking project that nothing here reads (removed from General Settings)
        if (Schema::hasTable('site_settings')) {
            DB::table('site_settings')->whereIn('key', ['module_kiosk_enabled', 'module_extra_services_enabled', 'module_private_events_enabled', 'module_commission_enabled', 'module_pos_shop_enabled'])->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desk_sold_by');
        });

        Schema::table('stables', function (Blueprint $table) {
            $table->dropColumn('pos_enabled');
        });
    }
};

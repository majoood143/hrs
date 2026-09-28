<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stable booking's money lives on a service order like every other paid order; the order also
 * remembers the stable, the stable's own gateway account when the money went there, and who holds
 * the money: platform (our merchant account) or stable (its own gateway, or paid at the stable).
 * On its own so the order tests (which do not build the stable tables) can run it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('stable_id')->nullable()->index();
            $table->unsignedBigInteger('stable_payment_account_id')->nullable();
            $table->string('collected_by', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex(['stable_id']);
            $table->dropColumn(['stable_id', 'stable_payment_account_id', 'collected_by']);
        });
    }
};

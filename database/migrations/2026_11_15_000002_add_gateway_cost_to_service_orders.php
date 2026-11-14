<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The gateway's estimated charge on a payment we collected, fixed when it was paid. */
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->decimal('gateway_cost', 10, 3)->default(0)->after('vat_on_commission');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn('gateway_cost');
        });
    }
};

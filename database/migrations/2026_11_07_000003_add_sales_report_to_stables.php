<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The scheduled sales report to a stable's owners (admin → the stable → Sales report). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stables', function (Blueprint $table) {
            // not in booking_settings: the owner's settings page rewrites that column whole
            $table->json('sales_report')->nullable()->after('booking_settings');
            $table->date('sales_report_sent_on')->nullable()->after('sales_report');
        });
    }

    public function down(): void
    {
        Schema::table('stables', fn (Blueprint $table) => $table->dropColumn(['sales_report', 'sales_report_sent_on']));
    }
};

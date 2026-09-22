<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // VAT on the commission we charge the client, worked out when the order is created (only if the
        // "VAT on commission" setting is on). Like the commission itself it is not part of what the customer
        // pays: it is one more thing the client owes us.
        Schema::table('service_orders', function (Blueprint $table) {
            $table->decimal('vat_on_commission', 10, 3)->default(0)->after('commission_value');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn('vat_on_commission');
        });
    }
};

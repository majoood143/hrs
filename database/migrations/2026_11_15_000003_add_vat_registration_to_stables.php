<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The stable supplies its sessions (we are its agent): VAT on a session's price is charged only
     * when the stable itself is VAT-registered, under its own number.
     */
    public function up(): void
    {
        Schema::table('stables', function (Blueprint $table) {
            $table->boolean('vat_registered')->default(false);
            $table->string('vat_number', 30)->nullable();
        });

        // the stable's number as it was when the booking was made, for its invoice
        Schema::table('service_orders', function (Blueprint $table) {
            $table->string('stable_vat_number', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stables', function (Blueprint $table) {
            $table->dropColumn(['vat_registered', 'vat_number']);
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn('stable_vat_number');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Every refund is a credit note against the receipt: CN-<year>-000001, in order, never reused. */
    public function up(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->string('credit_note_number', 20)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('order_refunds', function (Blueprint $table) {
            $table->dropUnique(['credit_note_number']);
            $table->dropColumn('credit_note_number');
        });
    }
};

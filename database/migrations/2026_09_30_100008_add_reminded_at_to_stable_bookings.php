<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When the customer was reminded of the session (the day before), so it is sent once. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stable_bookings', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stable_bookings', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};

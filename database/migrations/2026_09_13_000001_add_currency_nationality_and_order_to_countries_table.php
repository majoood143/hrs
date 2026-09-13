<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('currency_code', 3)->nullable()->after('phone_code');
            $table->string('nationality_en')->nullable()->after('currency_code');
            $table->string('nationality_ar')->nullable()->after('nationality_en');
            $table->unsignedInteger('order')->default(0)->after('nationality_ar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn(['currency_code', 'nationality_en', 'nationality_ar', 'order']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The app's main notifications switch: off, nothing at all is pushed to the phone. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('topics');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_devices', fn (Blueprint $table) => $table->dropColumn('enabled'));
    }
};

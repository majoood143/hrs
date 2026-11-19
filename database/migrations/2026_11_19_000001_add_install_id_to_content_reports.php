<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A report is kept once per app install (X-Install-Id), not per address: one mobile carrier
 * address is many phones, and a second person's report was dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('content_reports', 'install_id')) {
            return;
        }

        Schema::table('content_reports', function (Blueprint $table) {
            $table->string('install_id', 64)->nullable()->after('ip');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('content_reports', 'install_id')) {
            Schema::table('content_reports', fn (Blueprint $table) => $table->dropColumn('install_id'));
        }
    }
};

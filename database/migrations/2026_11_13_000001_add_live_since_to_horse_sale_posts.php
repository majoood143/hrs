<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a horse post last went live (posted, approved, or switched back on): its 60 days run from
 * then, not from when it was first submitted. Empty for older rows, which keep counting from created_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horse_sale_posts', function (Blueprint $table) {
            $table->timestamp('live_since')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('horse_sale_posts', function (Blueprint $table) {
            $table->dropColumn('live_since');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A stable's website and social accounts, shown on its page when filled. */
return new class extends Migration
{
    private const COLUMNS = ['website_url', 'instagram_url', 'x_url', 'facebook_url', 'tiktok_url', 'youtube_url'];

    public function up(): void
    {
        Schema::table('stables', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->string($column)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stables', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};

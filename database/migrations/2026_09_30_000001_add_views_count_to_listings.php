<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The public listings whose pages show a view counter (Support\ViewCounter).
     */
    private const TABLES = [
        'centers',
        'clinics',
        'events',
        'farriers',
        'horse_sale_posts',
        'shops',
        'tool_sale_posts',
        'transfer_posts',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'views_count')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->unsignedBigInteger('views_count')->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'views_count')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('views_count');
            });
        }
    }
};

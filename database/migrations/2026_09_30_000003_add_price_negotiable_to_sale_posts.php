<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sale listings whose price can be marked as negotiable.
     */
    private const TABLES = [
        'horse_sale_posts',
        'tool_sale_posts',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'price_negotiable')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->boolean('price_negotiable')->default(false)->after('price');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'price_negotiable')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('price_negotiable');
            });
        }
    }
};

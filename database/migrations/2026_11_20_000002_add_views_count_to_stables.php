<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Stable pages get the same view counter as the other listings (Support\ViewCounter). */
    public function up(): void
    {
        if (Schema::hasColumn('stables', 'views_count')) {
            return;
        }

        Schema::table('stables', function (Blueprint $table) {
            $table->unsignedBigInteger('views_count')->default(0);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('stables', 'views_count')) {
            Schema::table('stables', function (Blueprint $table) {
                $table->dropColumn('views_count');
            });
        }
    }
};

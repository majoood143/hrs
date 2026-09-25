<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('videos') || Schema::hasColumn('videos', 'views_count')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedBigInteger('views_count')->default(0);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('videos') || ! Schema::hasColumn('videos', 'views_count')) {
            return;
        }

        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });
    }
};

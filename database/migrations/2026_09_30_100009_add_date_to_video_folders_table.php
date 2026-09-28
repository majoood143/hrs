<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_folders', function (Blueprint $table) {
            $table->date('date')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('video_folders', function (Blueprint $table) {
            $table->dropColumn('date');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_folders', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('video_folders')->nullOnDelete();
            $table->index(['parent_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::table('video_folders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};

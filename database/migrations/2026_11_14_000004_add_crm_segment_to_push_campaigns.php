<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_campaigns', function (Blueprint $table) {
            // audience "segment": the signed-in phones of the segment's customers
            $table->foreignId('crm_segment_id')->nullable()->after('target_stables')->constrained('crm_segments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crm_segment_id');
        });
    }
};

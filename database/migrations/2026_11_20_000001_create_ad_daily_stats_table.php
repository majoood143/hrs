<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Impressions and clicks per promotion, per Oman day and per source (website or app). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ad_daily_stats')) {
            return;
        }

        Schema::create('ad_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained('ads')->cascadeOnDelete();
            $table->date('date');
            $table->string('source', 8);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);

            $table->unique(['ad_id', 'date', 'source']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_daily_stats');
    }
};

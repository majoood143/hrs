<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The WhatsApp messages sent to the admins with a race's results (top places, replay, results page):
 * one row per race, so the automatic check sends each race once and the admin page shows what went out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('race_result_alerts', function (Blueprint $table) {
            $table->id();
            // the racing source's race id (racing/results/{race})
            $table->unsignedBigInteger('race_id')->unique();
            $table->date('race_date');
            $table->string('race_name')->nullable();
            // when the automatic check first saw the results: it waits a while from here for the replay
            $table->timestamp('results_seen_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            // auto | manual
            $table->string('mode', 10)->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('recipients')->default(0);
            $table->string('video_url', 2048)->nullable();
            // the replay came after the results: a short follow-up with its link
            $table->timestamp('video_sent_at')->nullable();
            $table->timestamps();

            $table->index('race_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('race_result_alerts');
    }
};

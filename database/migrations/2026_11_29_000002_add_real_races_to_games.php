<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real races in game campaigns: races taken from the racing club's calendar (past or upcoming), each
 * with a copy of its declared field (and its official result once run), or typed in by an admin;
 * an imported race can also be corrected by hand. Players pick a race and a
 * horse; a race scores points (Services\Games\GameScore) instead of only place and time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_races', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_campaign_id')->constrained()->cascadeOnDelete();
            // club = read from the racing club's site (source_race_id); manual = typed in by an admin
            $table->string('source', 10)->default('club');
            $table->unsignedInteger('source_race_id')->nullable(); // the racing club's race id
            $table->date('race_date');
            $table->string('post_time', 5)->nullable();
            $table->string('en_name');
            $table->string('ar_name');
            $table->string('en_meeting')->nullable();
            $table->string('ar_meeting')->nullable();
            $table->unsignedSmallInteger('distance');
            $table->string('en_surface', 40)->nullable();
            $table->string('ar_surface', 40)->nullable();
            // the declared field: Models\GameRace::runners() describes a runner
            $table->json('runners');
            // a club race's runners changed (or added) by hand, and the ones taken out: "Refresh field" keeps both
            $table->json('locked_nos')->nullable();
            $table->json('removed_nos')->nullable();
            $table->unsignedInteger('winning_time_ms')->nullable();
            $table->boolean('has_result')->default(false);
            $table->dateTime('refreshed_at');
            $table->timestamps();

            $table->unique(['game_campaign_id', 'source_race_id']);
        });

        Schema::table('game_attempts', function (Blueprint $table) {
            $table->foreignId('game_race_id')->nullable()->after('game_campaign_id')->constrained()->nullOnDelete();
            $table->string('horse_no', 5)->nullable()->after('horse_style');
            $table->unsignedSmallInteger('horse_rating')->nullable()->after('horse_no');
            $table->unsignedTinyInteger('horse_rating_rank')->nullable()->after('horse_rating');
            $table->unsignedInteger('score')->nullable()->after('top_speed_kmh');
        });
    }

    public function down(): void
    {
        Schema::table('game_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('game_race_id');
            $table->dropColumn(['horse_no', 'horse_rating', 'horse_rating_rank', 'score']);
        });

        Schema::dropIfExists('game_races');
    }
};

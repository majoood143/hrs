<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Game campaigns (admin → Games): a period in which signed-in customers race the horse racing game
 * (`public/games/horse-racing`) on one race of its card, the prizes for the top ranks, every attempt
 * with the result the game reported and the server's checks on it, and the prizes handed out.
 * Players are customers (phone sign-in), so a player blocked from games is a column on them.
 */
return new class extends Migration
{
    private const MODELS = ['GameCampaign', 'GameAttempt'];

    private const ACTIONS = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];

    public function up(): void
    {
        Schema::create('game_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('en_name');
            $table->string('ar_name');
            $table->text('en_description')->nullable();
            $table->text('ar_description')->nullable();
            $table->text('en_prize_text')->nullable();
            $table->text('ar_prize_text')->nullable();
            $table->text('en_terms')->nullable();
            $table->text('ar_terms')->nullable();
            $table->string('en_sponsor')->nullable();
            $table->string('ar_sponsor')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('sponsor_logo')->nullable();
            $table->string('status', 20)->default('draft'); // draft | published | archived
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            // the race every attempt runs (Support\Games\RaceCatalog = the game's race card)
            $table->string('race_id', 40);
            $table->string('difficulty', 10)->default('normal');

            $table->unsignedSmallInteger('attempts_per_day')->nullable()->default(3);
            $table->unsignedSmallInteger('attempts_total')->nullable();
            $table->unsignedTinyInteger('risk_threshold')->default(60);

            $table->boolean('leaderboard_public')->default(true);
            $table->unsignedSmallInteger('leaderboard_size')->default(10);
            $table->dateTime('leaderboard_freeze_at')->nullable();

            $table->dateTime('awarded_at')->nullable();
            $table->unsignedBigInteger('awarded_by')->nullable(); // the admin (users), kept as a plain id like the auctions'
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });

        Schema::create('game_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rank');
            $table->string('en_title');
            $table->string('ar_title');
            $table->timestamps();

            $table->unique(['game_campaign_id', 'rank']);
        });

        Schema::create('game_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('game_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('started'); // started | completed | abandoned
            $table->string('horse_style', 20)->nullable();
            // what the game reported at the line
            $table->unsignedTinyInteger('position')->nullable();
            $table->unsignedTinyInteger('field_size')->nullable();
            $table->unsignedInteger('race_time_ms')->nullable();
            $table->decimal('top_speed_kmh', 5, 1)->nullable();
            // measured by the server, from the start call to the finish call
            $table->unsignedInteger('wall_ms')->nullable();
            $table->dateTime('started_at', 3);
            $table->dateTime('finished_at', 3)->nullable();
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->json('flags')->nullable();
            $table->string('review_status', 20)->nullable(); // flagged | cleared | invalidated
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['game_campaign_id', 'status', 'review_status']);
            $table->index(['customer_id', 'game_campaign_id', 'started_at']);
        });

        Schema::create('game_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_prize_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rank');
            $table->string('status', 20)->default('awarded'); // awarded | notified | void
            $table->dateTime('notified_at')->nullable();
            $table->string('notify_error')->nullable();
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dateTime('games_blocked_at')->nullable();
            $table->string('games_blocked_reason')->nullable();
        });

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $names = [];
        foreach (self::MODELS as $model) {
            foreach (self::ACTIONS as $action) {
                $names[] = Permission::findOrCreate("{$action}:{$model}", 'web')->name;
            }
        }

        // super admins hold each permission themselves here (Shield's gate bypass is off)
        Role::findOrCreate('super_admin', 'web')->givePermissionTo($names);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('game_awards');
        Schema::dropIfExists('game_attempts');
        Schema::dropIfExists('game_prizes');
        Schema::dropIfExists('game_campaigns');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['games_blocked_at', 'games_blocked_reason']);
        });
    }
};

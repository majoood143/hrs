<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * More control over a push campaign: who exactly (pasted phone numbers, a stable's customers or
 * owners), how it is delivered (priority, expiry, sound, Android channel) and when (quiet hours,
 * past the daily limit, spread over some minutes: the phones still to send and the next batch's time).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->json('target_phones')->nullable()->after('audience');
            $table->json('target_stables')->nullable()->after('target_phones');
            $table->string('priority', 6)->default('normal')->after('active_days');
            $table->unsignedSmallInteger('ttl_hours')->nullable()->after('priority');
            $table->boolean('sound')->default(true)->after('ttl_hours');
            $table->string('channel', 20)->default('default')->after('sound');
            $table->boolean('quiet_hours')->default(false)->after('channel');
            $table->boolean('ignore_cap')->default(false)->after('quiet_hours');
            $table->unsignedSmallInteger('spread_minutes')->nullable()->after('ignore_cap');
            $table->json('pending_devices')->nullable()->after('spread_minutes');
            $table->timestamp('next_batch_at')->nullable()->after('pending_devices');
        });
    }

    public function down(): void
    {
        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->dropColumn(['target_phones', 'target_stables', 'priority', 'ttl_hours', 'sound', 'channel',
                'quiet_hours', 'ignore_cap', 'spread_minutes', 'pending_devices', 'next_batch_at']);
        });
    }
};

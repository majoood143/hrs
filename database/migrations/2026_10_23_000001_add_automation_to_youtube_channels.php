<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per watched channel: whether new videos go straight into the video library (hidden or
 * published) instead of waiting in the inbox, and whether the admins get the WhatsApp alert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('youtube_channels', function (Blueprint $table) {
            // off | hidden | publish (YouTubeChannel::AUTO_ADD_*)
            $table->string('auto_add', 10)->default('off')->after('skip_shorts');
            $table->boolean('notify')->default(true)->after('auto_add');
        });
    }

    public function down(): void
    {
        Schema::table('youtube_channels', function (Blueprint $table) {
            $table->dropColumn(['auto_add', 'notify']);
        });
    }
};

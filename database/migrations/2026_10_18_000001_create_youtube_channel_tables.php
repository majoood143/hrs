<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Watched YouTube channels (other people's public channels) and the videos found on them: admins
 * get a WhatsApp alert for each new one and add the ones they want to the video library by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_channels', function (Blueprint $table) {
            $table->id();
            // UC… id: the feed and the API work by it, handles can change
            $table->string('channel_id', 64)->unique();
            $table->string('handle')->nullable();
            $table->string('title');
            $table->string('thumbnail_url', 2048)->nullable();
            $table->foreignId('video_folder_id')->nullable()->constrained('video_folders')->nullOnDelete();
            $table->boolean('skip_shorts')->default(true);
            $table->boolean('is_active')->default(true);
            // null until the first successful check, which only records what is already there
            $table->timestamp('checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();
        });

        Schema::create('youtube_channel_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('youtube_channel_id')->constrained('youtube_channels')->cascadeOnDelete();
            $table->string('youtube_id', 20)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            // video | short | live | upcoming | stream (a past live stream)
            $table->string('kind', 20)->default('video');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            // null = unknown (no API key, or the API did not answer)
            $table->boolean('embeddable')->nullable();
            // new | added | ignored | skipped
            $table->string('status', 20)->default('new');
            $table->foreignId('library_video_id')->nullable()->constrained('videos')->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_channel_videos');
        Schema::dropIfExists('youtube_channels');
    }
};

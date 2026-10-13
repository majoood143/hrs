<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('en_title');
            $table->string('ar_title');
            $table->text('en_body')->nullable();
            $table->text('ar_body')->nullable();
            $table->string('image_path')->nullable();
            // none | url | screen
            $table->string('action_type', 20)->default('none');
            $table->string('action_url', 2048)->nullable();
            $table->string('action_screen', 60)->nullable();
            $table->string('en_button_label')->nullable();
            $table->string('ar_button_label')->nullable();
            // empty = everyone
            $table->json('platforms')->nullable();
            $table->json('locales')->nullable();
            $table->string('min_version', 20)->nullable();
            $table->string('max_version', 20)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            // once | daily | every_launch
            $table->string('frequency', 20)->default('once');
            $table->boolean('dismissible')->default(true);
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_announcements');
    }
};

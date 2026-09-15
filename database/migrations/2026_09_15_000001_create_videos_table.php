<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('video_folders')->restrictOnDelete();
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('slug')->unique();
            $table->string('youtube_url');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['folder_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};

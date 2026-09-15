<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('ad_zones')->restrictOnDelete();
            $table->string('customer_name');
            $table->string('media_type');
            $table->string('image_path')->nullable();
            $table->string('video_path')->nullable();
            $table->json('alt_text')->nullable();
            $table->string('target_url')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['zone_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};

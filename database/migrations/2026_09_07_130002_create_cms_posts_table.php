<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_posts', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('excerpt')->nullable();
            $table->json('content')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('cms_categories')->nullOnDelete();
            $table->enum('status', ['draft', 'published', 'scheduled'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('template')->default('default');
            $table->string('container_width')->default('boxed');
            $table->boolean('show_title')->default(true);
            $table->text('custom_css')->nullable();
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->string('canonical_url')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_posts');
    }
};

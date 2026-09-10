<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horses', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('en_name');
            $table->string('cover_photo')->nullable()->after('is_featured');
            $table->text('public_story_en')->nullable()->after('cover_photo');
            $table->text('public_story_ar')->nullable()->after('public_story_en');

            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('horses', function (Blueprint $table) {
            $table->dropIndex(['is_featured']);
            $table->dropColumn(['is_featured', 'cover_photo', 'public_story_en', 'public_story_ar']);
        });
    }
};

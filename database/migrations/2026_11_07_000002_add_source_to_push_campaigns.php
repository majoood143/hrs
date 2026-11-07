<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An automatic campaign's new content (MobileAutoPush::SECTIONS key + its id): the pair is unique, so an item is announced once. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('id');
            $table->unsignedBigInteger('source_id')->nullable()->after('source');
            $table->unique(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('push_campaigns', function (Blueprint $table) {
            $table->dropUnique(['source', 'source_id']);
            $table->dropColumn(['source', 'source_id']);
        });
    }
};

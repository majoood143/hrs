<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Tell me about new ones like these": a phone's saved filters on the horses or tools board; a
 * post going live that matches them is pushed to the phone (App\Jobs\NotifySavedSearches).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_device_id')->constrained()->cascadeOnDelete();
            // PushSavedSearch::SECTIONS: horses | tools
            $table->string('section', 10);
            // the board's filters, named like its query string
            $table->json('filters');
            $table->timestamps();

            $table->index('section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_saved_searches');
    }
};

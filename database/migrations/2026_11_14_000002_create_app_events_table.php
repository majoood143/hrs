<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What people do in the app (and a few things only the server knows: bookings, packages), for
        // the CRM. One row per event; an install's events become the customer's when they sign in.
        Schema::create('app_events', function (Blueprint $table) {
            $table->id();
            // the app's anonymous id for this install (null for events from the server alone)
            $table->string('install_id', 64)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            // App\Models\AppEvent::CLIENT / SERVER
            $table->string('name', 40);
            $table->json('properties')->nullable();
            $table->string('screen', 120)->nullable();
            $table->string('platform', 10)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('locale', 5)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['customer_id', 'occurred_at']);
            $table->index(['install_id', 'occurred_at']);
            $table->index(['name', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_events');
    }
};

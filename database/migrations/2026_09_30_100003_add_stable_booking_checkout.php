<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout for stable bookings: a stable's own gateway keys (waiting for an admin until approved),
 * and what a booking records about how it is paid and how it ended.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stable_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->string('gateway', 16);
            // encrypted with the app key (Laravel's encrypted:array cast)
            $table->text('credentials');
            $table->boolean('test_mode')->default(true);
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->string('last_test_message')->nullable();
            $table->timestamps();

            $table->unique(['stable_id', 'gateway']);
        });

        Schema::table('stable_bookings', function (Blueprint $table) {
            $table->string('payment_option', 16)->default('online');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_source', 16)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('attended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stable_bookings', function (Blueprint $table) {
            $table->dropColumn(['payment_option', 'confirmed_at', 'cancelled_at', 'cancellation_source', 'cancellation_reason', 'attended_at']);
        });

        Schema::dropIfExists('stable_payment_accounts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stable owners: users who run one or more stables from the /stable panel. A stable they register
 * waits for an admin, who approves it and sets its commission. Stables that already exist were
 * created by an admin, so they start approved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable();
            // the language messages to this user go out in (stable owners pick it when they register)
            $table->string('locale', 8)->nullable();
        });

        Schema::table('stables', function (Blueprint $table) {
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('approval_status', 16)->default('approved');
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('commission_type', 16)->nullable();
            $table->decimal('commission_value', 10, 3)->nullable();
            $table->json('booking_settings')->nullable();

            $table->index('approval_status');
        });

        Schema::create('stable_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16)->default('owner');
            $table->timestamps();

            $table->unique(['stable_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stable_user');

        Schema::table('stables', function (Blueprint $table) {
            $table->dropIndex(['approval_status']);
            $table->dropColumn([
                'phone', 'email', 'approval_status', 'approved_at', 'approved_by', 'rejection_reason',
                'commission_type', 'commission_value', 'booking_settings',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_verified_at', 'locale']);
        });
    }
};

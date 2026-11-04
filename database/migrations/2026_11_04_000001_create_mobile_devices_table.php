<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phones that can receive push notifications (Expo push tokens): every phone that allowed them
 * (`POST devices`), linked to the customer and/or the stable owner signed in on it. Each link keeps
 * its sign-in token, so signing out (or the token expiring) ends that link without the app saying so.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->string('expo_token')->unique();
            // ios | android
            $table->string('platform', 10)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('locale', 5)->default('en');
            // the topics it takes campaigns about (MobileDevice::TOPICS); null = all
            $table->json('topics')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};

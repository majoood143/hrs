<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The people who order services. They sign in with their phone and an SMS code, so there is no
        // password and no link to the admin `users` table: a customer can never reach the admin panel.
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            // digits only, with the country code (App\Support\PhoneNumber)
            $table->string('phone', 32)->unique();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('locale', 5)->default('en');
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // One row per code sent. Only a keyed hash of the code is kept, never the code itself.
        Schema::create('customer_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32)->index();
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable()->index();
            $table->timestamps();

            $table->index(['phone', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_otps');
        Schema::dropIfExists('customers');
    }
};

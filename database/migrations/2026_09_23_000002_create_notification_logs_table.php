<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every SMS (and, from phase 4, email) we tried to send, so "did the customer get it?" has an answer.
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_order_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('channel', 16);            // sms | email
            $table->string('type', 32);               // otp | order_received | order_completed | test ...
            $table->string('recipient');
            $table->string('status', 16);             // sent | failed
            // Not stored for one-time codes.
            $table->text('message')->nullable();
            $table->string('provider_reference')->nullable();
            $table->text('error')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();

            $table->index(['channel', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every gateway session/payment id ever issued for an order, not just the current one on
     * service_orders.payment_session_id. Thawani and NBO both mint a brand new id each time
     * initiate() runs (a double-click, a back button, two tabs), which overwrites that single
     * column; without this history, a late callback or webhook for the now-orphaned earlier
     * session cannot be matched back to its order at all, and a captured payment is lost until
     * someone reconciles payment_gateway_logs by hand.
     */
    public function up(): void
    {
        Schema::create('payment_gateway_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('gateway', 16);
            $table->string('session_id');
            $table->timestamps();

            $table->unique(['gateway', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_sessions');
    }
};

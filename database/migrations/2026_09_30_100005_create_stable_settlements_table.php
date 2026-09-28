<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money that moved between us and a stable outside the gateways, as an admin records it: a payout
 * (we paid the stable its share of bookings we collected) or a receipt (the stable paid us the fees
 * and commission on bookings it collected itself). With the bookings, they make the stable's balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stable_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->string('direction', 16);
            $table->decimal('amount', 10, 3);
            $table->date('paid_on');
            $table->string('method', 32)->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['stable_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stable_settlements');
    }
};

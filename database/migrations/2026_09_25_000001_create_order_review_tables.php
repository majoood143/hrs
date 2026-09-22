<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The review stages an order has to pass, copied from the form's settings when the order comes in,
        // so editing the form later never changes an order already under review. Stages run in position
        // order; anyone holding the stage's role may decide it.
        Schema::create('order_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->json('name');
            $table->string('role_name', 64);
            $table->string('status', 16)->default('pending');   // pending | approved | rejected
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['service_order_id', 'position']);
        });

        // Money handed back to the customer: the price and its VAT, never the service fee. The refund itself is
        // made at the payment gateway (or by bank transfer); this is the record of it.
        Schema::create('order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->decimal('amount', 10, 3);
            $table->text('reason')->nullable();
            $table->string('method', 16)->default('manual');    // gateway | manual
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();
        });

        // Files an admin hands to the customer as the result of the request. Stored on the private disk.
        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('title');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_documents');
        Schema::dropIfExists('order_refunds');
        Schema::dropIfExists('order_stages');
    }
};

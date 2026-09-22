<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();
            $table->string('receipt_number', 32)->nullable()->unique();

            // Where the order came from. Plain indexed columns, no foreign keys: the form
            // builder's table names are configurable and orders must outlive a deleted form.
            $table->unsignedBigInteger('form_id')->nullable()->index();
            $table->unsignedBigInteger('submission_id')->nullable()->index();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 32)->nullable()->index();
            // The language the customer used, so later emails / SMS / receipts match it.
            $table->string('locale', 5)->default('en');

            // Money snapshot, taken when the order is created (3 decimals: baisa).
            // total = price + fee_amount + vat_on_price + vat_on_fee
            $table->char('currency', 3)->default('OMR');
            $table->decimal('price', 10, 3)->default(0);
            $table->decimal('fee_amount', 10, 3)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_on_price', 10, 3)->default(0);
            $table->decimal('vat_on_fee', 10, 3)->default(0);
            $table->decimal('total', 10, 3)->default(0);
            // The fee rule that produced fee_amount, kept even if the rule is edited later.
            $table->unsignedBigInteger('service_fee_setting_id')->nullable();
            $table->string('service_fee_type', 16)->nullable();
            $table->decimal('service_fee_value', 10, 3)->nullable();
            // Optional platform commission taken out of the client's share (second line of income).
            $table->decimal('commission_amount', 10, 3)->default(0);
            // Refunds return the price and its VAT; the fee (and its VAT) is kept.
            $table->decimal('refunded_amount', 10, 3)->default(0);

            $table->string('payment_status', 16)->default('pending')->index();
            $table->string('payment_method', 16)->nullable();
            $table->string('payment_session_id')->nullable()->index();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable()->index();

            // The work status: what the admins are doing with the request.
            $table->string('status', 24)->default('pending_payment')->index();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // system (expiry sweep) | gateway | customer | admin: only the first two may be revived by a late payment.
            $table->string('cancellation_source', 16)->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('message')->nullable();
            $table->json('data')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            // Public events are shown to the customer on the order page.
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index(['service_order_id', 'created_at']);
        });

        Schema::create('payment_gateway_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->nullable()->constrained('service_orders')->nullOnDelete();
            $table->string('gateway');
            $table->string('event');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();

            $table->index(['gateway', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_logs');
        Schema::dropIfExists('order_events');
        Schema::dropIfExists('service_orders');
    }
};

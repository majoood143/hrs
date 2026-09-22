<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A platform commission taken out of the CLIENT's share of an order (its price), never charged to the
        // customer on top: the second line of income next to the service fee. Same scope as a fee rule,
        // most specific first: a form, then a service, then global. No rule means no commission.
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('commission_type', 16)->default('percentage');
            $table->decimal('commission_value', 10, 3);
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'is_active']);
            $table->index(['service_id', 'is_active']);
        });

        // service_orders already has commission_amount (default 0); the rule behind it is kept too, so the
        // amount stays explainable after the rule is edited.
        Schema::table('service_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('commission_setting_id')->nullable()->after('commission_amount');
            $table->string('commission_type', 16)->nullable()->after('commission_setting_id');
            $table->decimal('commission_value', 10, 3)->nullable()->after('commission_type');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn(['commission_setting_id', 'commission_type', 'commission_value']);
        });

        Schema::dropIfExists('commission_settings');
    }
};

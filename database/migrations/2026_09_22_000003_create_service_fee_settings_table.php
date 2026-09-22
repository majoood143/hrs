<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer-facing service fees, added ON TOP of a service's price (unlike a commission,
 * which comes out of the client's share). Scope, most specific first: a form, then a
 * service, then global.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('fee_type', 16)->default('percentage');
            $table->decimal('fee_value', 10, 3);
            $table->json('name')->nullable();
            $table->json('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'is_active']);
            $table->index(['service_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_fee_settings');
    }
};

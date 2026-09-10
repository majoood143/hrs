<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transfer_posts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['offer', 'request']);

            $table->foreignId('from_country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('from_region_id')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('from_city_id')->constrained('cities')->cascadeOnDelete();

            $table->foreignId('to_country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('to_region_id')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('to_city_id')->constrained('cities')->cascadeOnDelete();

            $table->unsignedInteger('capacity');
            $table->date('transfer_date');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('contact_number');

            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');

            $table->timestamps();

            $table->index('transfer_date');
            $table->index('status');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_posts');
    }
};

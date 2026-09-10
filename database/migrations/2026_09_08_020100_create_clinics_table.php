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
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();

            $table->string('en_name');
            $table->string('ar_name');
            $table->string('slug')->unique();

            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('address')->nullable();
            $table->string('map_link')->nullable();

            $table->text('en_description')->nullable();
            $table->text('ar_description')->nullable();

            $table->string('cover_photo')->nullable();
            $table->json('gallery')->nullable();
            $table->json('opening_hours')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('city_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinics');
    }
};

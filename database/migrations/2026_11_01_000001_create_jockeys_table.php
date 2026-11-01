<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jockeys', function (Blueprint $table) {
            $table->id();

            $table->string('en_name');
            $table->string('ar_name');
            /** Jockey::TYPES they ride (flat racing, endurance…). */
            $table->json('types');
            $table->unsignedTinyInteger('years_experience')->nullable();

            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();

            $table->string('cover_photo')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('contact_number');

            // The racing club's jockey id (RacingClient profile); shown only once an admin checked it is them.
            $table->unsignedBigInteger('racing_jockey_id')->nullable();
            $table->boolean('racing_verified')->default(false);

            $table->string('status')->default('active');
            $table->unsignedBigInteger('views_count')->default(0);

            $table->timestamps();

            $table->index('status');
            $table->index('city_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jockeys');
    }
};

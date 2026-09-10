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
        Schema::create('horse_sale_posts', function (Blueprint $table) {
            $table->id();

            $table->string('en_name');
            $table->string('ar_name');

            $table->foreignId('type_id')->constrained('types')->cascadeOnDelete();
            $table->foreignId('gender_id')->constrained('genders')->cascadeOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('colors')->nullOnDelete();
            $table->string('breed')->nullable();
            $table->date('dob')->nullable();

            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();

            $table->decimal('price', 10, 2);
            $table->string('cover_photo');
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('contact_number');

            $table->enum('status', ['active', 'sold', 'expired', 'cancelled'])->default('active');

            $table->timestamps();

            $table->index('status');
            $table->index('type_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horse_sale_posts');
    }
};

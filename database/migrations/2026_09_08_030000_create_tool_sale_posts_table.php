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
        Schema::create('tool_sale_posts', function (Blueprint $table) {
            $table->id();

            $table->string('en_name');
            $table->string('ar_name');

            $table->enum('category', [
                'saddlery_tack',
                'grooming',
                'farrier_tools',
                'feeding_stable',
                'health_first_aid',
                'transport_equipment',
                'other',
            ]);
            $table->enum('condition', ['new', 'used']);
            $table->string('brand')->nullable();

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
            $table->index('category');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_sale_posts');
    }
};

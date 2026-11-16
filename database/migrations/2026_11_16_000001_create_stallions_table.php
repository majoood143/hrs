<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stallions', function (Blueprint $table) {
            $table->id();

            $table->string('en_name');
            $table->string('ar_name');
            /** Country of birth, the catalogue's "(FR)" code. */
            $table->string('origin', 3)->nullable();
            /** Stallion::COLOURS */
            $table->string('colour')->nullable();
            $table->unsignedSmallInteger('foaled_year')->nullable();

            $table->string('sire_en')->nullable();
            $table->string('sire_ar')->nullable();
            $table->string('dam_en')->nullable();
            $table->string('dam_ar')->nullable();
            /** The 12 ancestors behind the sire and dam (Stallion::PEDIGREE_KEYS => "Name (FR)"), Latin like international pedigrees. */
            $table->json('pedigree')->nullable();
            /** An outside pedigree page (e.g. allbreedpedigree), instead of or besides the tree. */
            $table->string('pedigree_url')->nullable();

            // the catalogue's race record; the live history comes from the racing club through racing_horse_id
            $table->unsignedSmallInteger('runs')->nullable();
            $table->unsignedSmallInteger('wins')->nullable();
            $table->unsignedSmallInteger('places')->nullable();
            $table->unsignedSmallInteger('rating')->nullable();
            $table->unsignedBigInteger('racing_horse_id')->nullable();
            $table->text('highlights_en')->nullable();
            $table->text('highlights_ar')->nullable();

            $table->string('stud_en')->nullable();
            $table->string('stud_ar')->nullable();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('contact_number')->nullable();
            $table->string('cover_photo')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stallions');
    }
};

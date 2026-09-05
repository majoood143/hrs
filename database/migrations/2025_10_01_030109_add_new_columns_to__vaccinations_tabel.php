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
        Schema::table('Vaccinations', function (Blueprint $table) {
            //
            // Core vaccine information
            // $table->enum('name', [
            //     'Tetanus',
            //     'Eastern/Western Encephalomyelitis',
            //     'West Nile Virus',
            //     'Rabies',
            //     'Influenza',
            //     'Rhinopneumonitis',
            //     'Strangles',
            //     'Potomac Horse Fever',
            //     'Botulism',
            //     'Rotavirus',
            //     'Other', // For custom/regional vaccines
            // ]);

            // // If 'Other' is selected, store the custom vaccine name
            // $table->string('custom_vaccine_name')->nullable();

            // Date tracking
            //$table->date('date_given')->nullable();
            //$table->date('next_due_date')->nullable();

            // Relationships
            $table->unsignedBigInteger('horse_id')->nullable();
            $table->foreign('horse_id')->references('id')->on('horses')->onDelete('cascade');
            //$table->foreignId('user_id')->constrained()->cascadeOnDelete();
            //$table->foreignId('horse_id')->nullable()->constrained()->cascadeOnDelete();

            // Additional vaccine details
            $table->string('manufacturer')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('veterinarian_name')->nullable();
            $table->string('veterinarian_license')->nullable();
            $table->decimal('dosage', 8, 2)->nullable(); // In ml
            $table->enum('administration_route', [
                'Intramuscular',
                'Subcutaneous',
                'Intranasal',
                'Oral',
            ])->nullable();

            // Reaction tracking
            $table->boolean('adverse_reaction')->default(false);
            $table->text('reaction_details')->nullable();

            // General notes
            $table->text('notes')->nullable();

            //$table->index('user_id');
            $table->index('horse_id');
            $table->index('next_due_date');
            $table->index(['user_id', 'next_due_date']); // Composite for reminders
            $table->index('date_given');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Vaccinations', function (Blueprint $table) {
            //
        });
    }
};

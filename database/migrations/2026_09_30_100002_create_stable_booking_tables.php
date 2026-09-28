<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a stable sells (offerings, e.g. riding training), when (weekly schedules turned into dated
 * slots, minus closures), who runs it (optional trainers and horses), and the bookings that hold
 * places in a slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stable_trainers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->string('en_name');
            $table->string('ar_name')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stable_horses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stable_offerings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->string('type', 32)->default('riding_training');
            $table->string('en_name');
            $table->string('ar_name');
            $table->text('en_description')->nullable();
            $table->text('ar_description')->nullable();
            $table->string('photo')->nullable();
            $table->decimal('price', 10, 3);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('min_riders')->default(1);
            $table->unsignedSmallInteger('max_riders')->default(1);
            $table->unsignedInteger('booking_cutoff_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['stable_id', 'is_active']);
        });

        Schema::create('stable_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->foreignId('stable_offering_id')->constrained('stable_offerings')->cascadeOnDelete();
            $table->json('weekdays');
            $table->json('start_times');
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->foreignId('trainer_id')->nullable()->constrained('stable_trainers')->nullOnDelete();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stable_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->foreignId('stable_offering_id')->nullable()->constrained('stable_offerings')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['stable_id', 'starts_on', 'ends_on']);
        });

        Schema::create('booking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->foreignId('stable_offering_id')->constrained('stable_offerings')->cascadeOnDelete();
            $table->foreignId('stable_schedule_id')->nullable()->constrained('stable_schedules')->nullOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('capacity');
            $table->boolean('is_open')->default(true);
            $table->foreignId('trainer_id')->nullable()->constrained('stable_trainers')->nullOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['stable_offering_id', 'date', 'start_time']);
            $table->index(['date', 'is_open']);
            $table->index(['stable_id', 'date']);
        });

        Schema::create('stable_bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->foreignId('booking_slot_id')->constrained('booking_slots')->restrictOnDelete();
            $table->foreignId('stable_offering_id')->constrained('stable_offerings')->cascadeOnDelete();
            $table->unsignedBigInteger('service_order_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedSmallInteger('riders');
            $table->json('riders_data')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            $table->index(['booking_slot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stable_bookings');
        Schema::dropIfExists('booking_slots');
        Schema::dropIfExists('stable_closures');
        Schema::dropIfExists('stable_schedules');
        Schema::dropIfExists('stable_offerings');
        Schema::dropIfExists('stable_horses');
        Schema::dropIfExists('stable_trainers');
    }
};

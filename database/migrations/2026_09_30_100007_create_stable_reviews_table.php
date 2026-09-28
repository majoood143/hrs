<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A customer's rating of a session they attended: one per booking, shown on the stable's page. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stable_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->foreignId('stable_booking_id')->unique()->constrained('stable_bookings')->cascadeOnDelete();
            $table->foreignId('stable_offering_id')->nullable()->constrained('stable_offerings')->nullOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('author_name')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->index(['stable_id', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stable_reviews');
    }
};

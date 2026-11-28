<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extras a customer adds to a booked session (photos, helmet hire, transport…), run by each stable's
 * owner. A booking keeps a copy of what it took, so editing an extra never changes an old booking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stables', function (Blueprint $table) {
            $table->boolean('extras_enabled')->default(true)->after('pos_enabled');
        });

        Schema::create('stable_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained()->cascadeOnDelete();
            $table->string('en_name');
            $table->string('ar_name');
            $table->text('en_description')->nullable();
            $table->text('ar_description')->nullable();
            $table->string('photo')->nullable();
            $table->decimal('price', 10, 3);
            $table->string('pricing', 20)->default('per_booking');
            $table->unsignedSmallInteger('max_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // none = every service of the stable
        Schema::create('stable_extra_offering', function (Blueprint $table) {
            $table->foreignId('stable_extra_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stable_offering_id')->constrained()->cascadeOnDelete();
            $table->primary(['stable_extra_id', 'stable_offering_id']);
        });

        Schema::create('stable_booking_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stable_extra_id')->nullable()->constrained()->nullOnDelete();
            $table->string('en_name');
            $table->string('ar_name');
            $table->decimal('unit_price', 10, 3);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('amount', 10, 3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stable_booking_extras');
        Schema::dropIfExists('stable_extra_offering');
        Schema::dropIfExists('stable_extras');

        Schema::table('stables', function (Blueprint $table) {
            $table->dropColumn('extras_enabled');
        });
    }
};

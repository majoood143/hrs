<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lesson packages: a stable sells a number of sessions of one service at a package price, used
 * within so many days. A customer's purchase is paid like a booking (a service order); the
 * bookings made with it are what use its sessions up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stable_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->foreignId('stable_offering_id')->constrained('stable_offerings')->cascadeOnDelete();
            $table->string('en_name');
            $table->string('ar_name');
            $table->text('en_description')->nullable();
            $table->text('ar_description')->nullable();
            $table->unsignedSmallInteger('sessions');
            $table->decimal('price', 10, 3);
            $table->unsignedSmallInteger('validity_days')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stable_package_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->foreignId('stable_package_id')->constrained('stable_packages')->restrictOnDelete();
            $table->foreignId('stable_offering_id')->constrained('stable_offerings')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id')->index();
            $table->unsignedBigInteger('service_order_id')->nullable()->index();
            // a snapshot of what was bought: editing the package later changes no purchase
            $table->string('name');
            $table->unsignedSmallInteger('sessions');
            $table->unsignedSmallInteger('validity_days');
            $table->string('status', 16)->default('pending');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::table('stable_bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('stable_package_purchase_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('stable_bookings', function (Blueprint $table) {
            $table->dropIndex(['stable_package_purchase_id']);
            $table->dropColumn('stable_package_purchase_id');
        });
        Schema::dropIfExists('stable_package_purchases');
        Schema::dropIfExists('stable_packages');
    }
};

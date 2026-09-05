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
        Schema::table('users', function (Blueprint $table) {
            //
            $table->string('civiled_id')->nullable()->after('name');
            $table->boolean('is_admin')->default(false)->after('password');
            $table->enum('type', ['owner','stable_owner','trainer', 'trainer_assistant', 'veterinarian', 'farrier', 'jockey', 'groom', 'admin'])->default('owner')->after('is_admin');
            $table->string('phone')->nullable()->after('type');
            $table->string('address')->nullable()->after('phone');
            $table->string('city')->nullable()->after('address');
            $table->string('region')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('region');
            $table->string('profile_photo_path', 2048)->nullable()->after('postal_code');
            $table->string('cr_number')->nullable()->after('profile_photo_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};

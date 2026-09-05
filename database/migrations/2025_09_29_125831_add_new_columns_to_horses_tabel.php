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
        Schema::table('horses', function (Blueprint $table) {
            //
            

            $table->date('dob')->nullable();
            $table->unsignedBigInteger('color_id')->nullable();
            $table->foreign('id')->references('id')->on('colors')->onDelete('cascade');
            $table->string('breed')->nullable();
            $table->string('microship')->nullable();
            $table->string('registration_number')->nullable();
            $table->unsignedBigInteger('dam_id')->nullable();
            $table->foreign('dam_id')->references('id')->on('horses')->onDelete('cascade');
            $table->unsignedBigInteger('sire_id')->nullable();
            $table->foreign('sire_id')->references('id')->on('horses')->onDelete('cascade');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('horses', function (Blueprint $table) {
            //
        });
    }
};

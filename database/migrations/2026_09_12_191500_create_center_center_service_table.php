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
        Schema::create('center_center_service', function (Blueprint $table) {
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('center_service_id')->constrained('center_services')->cascadeOnDelete();
            $table->primary(['center_id', 'center_service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('center_center_service');
    }
};

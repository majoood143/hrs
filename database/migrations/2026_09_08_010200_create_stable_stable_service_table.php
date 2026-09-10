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
        Schema::create('stable_stable_service', function (Blueprint $table) {
            $table->foreignId('stable_id')->constrained('stables')->cascadeOnDelete();
            $table->foreignId('stable_service_id')->constrained('stable_services')->cascadeOnDelete();
            $table->primary(['stable_id', 'stable_service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stable_stable_service');
    }
};

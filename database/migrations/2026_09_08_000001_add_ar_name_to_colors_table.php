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
        Schema::table('colors', function (Blueprint $table) {
            $table->renameColumn('name', 'en_name');
        });

        Schema::table('colors', function (Blueprint $table) {
            $table->string('ar_name')->after('en_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colors', function (Blueprint $table) {
            $table->dropColumn('ar_name');
        });

        Schema::table('colors', function (Blueprint $table) {
            $table->renameColumn('en_name', 'name');
        });
    }
};

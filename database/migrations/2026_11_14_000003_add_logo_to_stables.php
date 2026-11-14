<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A stable's profile picture, shown in a circle over its cover photo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stables', function (Blueprint $table) {
            $table->string('logo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stables', function (Blueprint $table) {
            $table->dropColumn('logo');
        });
    }
};

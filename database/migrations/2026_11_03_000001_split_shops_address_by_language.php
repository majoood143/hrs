<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A shop's address in both languages, like its name (the existing address becomes the English one). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->renameColumn('address', 'en_address');
        });
        Schema::table('shops', function (Blueprint $table) {
            $table->string('ar_address')->nullable()->after('en_address');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('ar_address');
        });
        Schema::table('shops', function (Blueprint $table) {
            $table->renameColumn('en_address', 'address');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Services become the priced catalogue behind paid forms: an Arabic name and
 * description (the English ones stay in name / description, like the app's other
 * simple models) and a 3-decimal price, since OMR has baisa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('ar_name')->nullable()->after('name');
            $table->text('ar_description')->nullable()->after('description');
            $table->decimal('price', 10, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['ar_name', 'ar_description']);
            $table->decimal('price', 8, 2)->change();
        });
    }
};

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
        Schema::table('horse_sale_posts', function (Blueprint $table) {
            $table->string('dam')->nullable()->after('breed');
            $table->string('sire')->nullable()->after('dam');
            $table->foreignId('birth_country_id')->nullable()->after('sire')->constrained('countries')->nullOnDelete();
            $table->string('passport_number')->nullable()->after('birth_country_id');
            $table->string('passport_document')->nullable()->after('passport_number');
            $table->json('images')->nullable()->after('cover_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('horse_sale_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('birth_country_id');
            $table->dropColumn(['dam', 'sire', 'passport_number', 'passport_document', 'images']);
        });
    }
};

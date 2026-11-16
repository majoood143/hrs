<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The app profile's photo and where they live (a public country and one of its regions), for the CRM.
        Schema::table('customers', function (Blueprint $table) {
            // on the public disk, customers/photos
            $table->string('profile_photo')->nullable()->after('name');
            // no foreign keys: a deleted country/region just stops showing (the relation is null)
            $table->unsignedBigInteger('country_id')->nullable()->index()->after('country');
            $table->unsignedBigInteger('region_id')->nullable()->index()->after('country_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['country_id']);
            $table->dropIndex(['region_id']);
            $table->dropColumn(['profile_photo', 'country_id', 'region_id']);
        });
    }
};

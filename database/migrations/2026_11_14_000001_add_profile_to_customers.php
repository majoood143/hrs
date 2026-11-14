<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The app's profile (all optional, filled in after the phone sign-in), for the CRM.
        Schema::table('customers', function (Blueprint $table) {
            // ISO 3166 alpha-2 of the phone's country, as picked in the app
            $table->string('country', 2)->nullable()->after('phone');
            // male | female
            $table->string('gender', 10)->nullable()->after('email');
            $table->date('date_of_birth')->nullable()->after('gender');
            // encrypted (Customer casts): never readable from the database alone
            $table->text('national_id')->nullable()->after('date_of_birth');
            $table->boolean('marketing_opt_in')->default(false)->after('national_id');
            // where the account was first made: web | app
            $table->string('source', 10)->default('web')->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['country', 'gender', 'date_of_birth', 'national_id', 'marketing_opt_in', 'source']);
        });
    }
};

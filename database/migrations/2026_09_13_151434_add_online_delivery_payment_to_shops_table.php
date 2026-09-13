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
        Schema::table('shops', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->after('is_active');
            $table->string('delivery_scope')->nullable()->after('is_online');
            $table->json('payment_options')->nullable()->after('delivery_scope');

            $table->index('is_online');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropIndex(['is_online']);
            $table->dropColumn(['is_online', 'delivery_scope', 'payment_options']);
        });
    }
};

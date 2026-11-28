<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phones the app was removed from are kept (dated) instead of deleted, so the admin can count them;
 * Expo's tickets wait in push_receipts until its receipts say whether Apple/Google still know the phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->timestamp('uninstalled_at')->nullable()->after('last_seen_at')->index();
        });

        Schema::create('push_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_id', 64);
            $table->foreignId('mobile_device_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_receipts');
        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->dropIndex(['uninstalled_at']);
            $table->dropColumn('uninstalled_at');
        });
    }
};

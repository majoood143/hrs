<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `pending` = posted from the app while admin → Mobile app → Posting → "Review app posts" is on:
 * hidden everywhere (`visible()` = active only) until an admin approves it.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> the statuses before this migration */
    private const STATUSES = [
        'horse_sale_posts' => ['active', 'sold', 'expired', 'cancelled'],
        'tool_sale_posts' => ['active', 'sold', 'expired', 'cancelled'],
        'farriers' => ['active', 'inactive'],
        'transfer_posts' => ['active', 'expired', 'cancelled'],
    ];

    public function up(): void
    {
        foreach (self::STATUSES as $table => $statuses) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($statuses) {
                $blueprint->enum('status', [...$statuses, 'pending'])->default('active')->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::STATUSES as $table => $statuses) {
            // a pending post would not fit the old list: it was never published, so it goes back to the admins' "off" status
            DB::table($table)->where('status', 'pending')->update(['status' => $table === 'farriers' ? 'inactive' : 'cancelled']);

            Schema::table($table, function (Blueprint $blueprint) use ($statuses) {
                $blueprint->enum('status', $statuses)->default('active')->change();
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries the site and admin actually run, found by EXPLAIN (full scans and
 * filesorts). Foreign keys, slugs and most status columns were indexed already.
 *
 * A composite index also serves any query on its leading column, so the single-column index it
 * replaces is dropped (keeping both only slows writes). Each step checks the index first, so the
 * migration is safe on a database whose indexes differ from the local one.
 */
return new class extends Migration
{
    /** @var array<string, list<array{add: list<string>, drop?: list<string>}>> */
    private array $indexes = [
        // Public directory listings: `active()` + default sort by name.
        'stables' => [['add' => ['is_active', 'en_name'], 'drop' => ['is_active']]],
        'centers' => [['add' => ['is_active', 'en_name'], 'drop' => ['is_active']]],
        'clinics' => [['add' => ['is_active', 'en_name'], 'drop' => ['is_active']]],
        'shops' => [['add' => ['is_active', 'en_name'], 'drop' => ['is_active']]],
        'transfer_posts' => [
            // Transfer board (`active()`: status + upcoming date, sorted by date) and the expiry sweep.
            ['add' => ['status', 'transfer_date'], 'drop' => ['status']],
            // Admin table's default sort.
            ['add' => ['created_at']],
        ],
        // Blog: `published()` sorted by published_at.
        'cms_posts' => [['add' => ['status', 'published_at'], 'drop' => ['status']]],
        'customer_otps' => [
            // The per-IP rate limit counts the last hour.
            ['add' => ['ip', 'created_at'], 'drop' => ['ip']],
            // Already covered by (phone, created_at).
            ['drop' => ['phone']],
        ],
        'service_orders' => [
            // Admin table's default sort.
            ['add' => ['created_at']],
            // Already covered by (status, created_at).
            ['drop' => ['status']],
        ],
        // Admin tables' default sorts.
        'notification_logs' => [['add' => ['created_at']]],
        'payment_gateway_logs' => [['add' => ['created_at']]],
        'customers' => [['add' => ['last_login_at']]],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $steps) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($steps as $step) {
                // Add before dropping, so the table is never without an index for those queries.
                if (isset($step['add'])) {
                    $this->addIndex($table, $step['add']);
                }
                if (isset($step['drop'])) {
                    $this->dropIndex($table, $step['drop']);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->indexes, true) as $table => $steps) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_reverse($steps) as $step) {
                if (isset($step['drop'])) {
                    $this->addIndex($table, $step['drop']);
                }
                if (isset($step['add'])) {
                    $this->dropIndex($table, $step['add']);
                }
            }
        }
    }

    /** @param  list<string>  $columns */
    private function addIndex(string $table, array $columns): void
    {
        if (! Schema::hasIndex($table, $columns)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns));
        }
    }

    /** @param  list<string>  $columns */
    private function dropIndex(string $table, array $columns): void
    {
        $name = $table.'_'.implode('_', $columns).'_index';

        if (Schema::hasIndex($table, $name)) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
        }
    }
};

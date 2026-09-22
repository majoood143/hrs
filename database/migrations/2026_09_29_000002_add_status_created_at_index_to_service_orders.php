<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both 5-minute sweeps (orders:expire-pending, orders:recover-late-payments) filter first by
     * `status`, then by a `created_at` range; `status` alone is already indexed, but pairing it
     * with `created_at` avoids scanning every order of that status to apply the range. Cancelled
     * orders in particular only ever accumulate (nothing prunes them), so this index matters more
     * as the table grows, not less.
     */
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
        });
    }
};

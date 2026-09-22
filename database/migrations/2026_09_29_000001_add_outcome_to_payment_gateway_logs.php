<?php

use App\Models\PaymentGatewayLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `PaymentGatewayLogResource`'s outcome filter/badges/widgets used to run a MySQL-only JSON
     * CASE expression per row (ClassifiesGatewayOutcome::outcomeCaseSql()) with no usable index —
     * a full table scan that gets slower as this table grows (every gateway call logs a row).
     * Persisting the outcome once, at write time, turns that into a plain indexed equality match.
     * Rows are never edited after creation, so a write-once column can never go stale.
     */
    public function up(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->string('outcome', 16)->nullable()->after('response_payload');
        });

        DB::table('payment_gateway_logs')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $payload = is_string($row->response_payload) ? (json_decode($row->response_payload, true) ?: []) : [];

                DB::table('payment_gateway_logs')
                    ->where('id', $row->id)
                    ->update(['outcome' => PaymentGatewayLog::classify($row->gateway, $payload)]);
            }
        });

        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->index(['outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_logs', function (Blueprint $table) {
            $table->dropIndex(['outcome', 'created_at']);
            $table->dropColumn('outcome');
        });
    }
};

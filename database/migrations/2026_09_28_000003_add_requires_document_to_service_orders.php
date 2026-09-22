<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A snapshot of the form's "requires a result document to complete" setting at the moment
     * the order was created, the same way order_stages snapshots the review stages. Without it,
     * OrderWorkflow::completionBlockers() read the form's *live* setting: flipping it on later
     * would retroactively block orders already mid-review that were never scoped to need one,
     * and deleting the form would silently skip the check for an order that did need one.
     */
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->boolean('requires_document')->default(false)->after('form_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropColumn('requires_document');
        });
    }
};

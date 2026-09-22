<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A submission becomes at most one order (CreateOrderFromSubmission checks-then-creates,
     * with no lock); without this constraint a retried/duplicated SubmissionReceived event can
     * create two orders, two money snapshots and two payment sessions for the same submission.
     * The column stays nullable — orders created outside the form-submission flow have none —
     * and MySQL's unique index does not compare NULLs to each other, so that keeps working.
     */
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            // Replaces the plain index from the create-table migration: a unique index serves
            // lookups just as well and additionally backs this constraint.
            $table->dropIndex(['submission_id']);
            $table->unique('submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropUnique(['submission_id']);
            $table->index('submission_id');
        });
    }
};

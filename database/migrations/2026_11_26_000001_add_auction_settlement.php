<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * After the hammer (phase 3): the winner's invoice and its due date, the hand-over, the seller's
 * payout, deposits kept from bidders who did not pay, and how to pay by bank transfer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            /** Bank details etc. for invoices above the card limit, shown to winners. */
            $table->text('en_payment_note')->nullable()->after('payment_days');
            $table->text('ar_payment_note')->nullable()->after('en_payment_note');
        });

        Schema::table('auction_lots', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_order_id')->nullable()->after('closed_at');
            $table->timestamp('payment_due_at')->nullable()->after('invoice_order_id');
            $table->timestamp('invoice_reminded_at')->nullable()->after('payment_due_at');
            $table->timestamp('payment_overdue_at')->nullable()->after('invoice_reminded_at');
            $table->timestamp('handed_over_at')->nullable()->after('payment_overdue_at');
            /** hammer + VAT on it − commission − entry fee, fixed with the invoice */
            $table->decimal('payout_amount', 10, 3)->nullable()->after('handed_over_at');
            $table->timestamp('payout_paid_at')->nullable()->after('payout_amount');
            $table->string('payout_reference')->nullable()->after('payout_paid_at');
            $table->index('invoice_order_id');
        });

        Schema::table('auction_bidders', function (Blueprint $table) {
            /** The deposit was kept (an unpaid invoice); otherwise it is refunded after the sale. */
            $table->timestamp('deposit_kept_at')->nullable()->after('deposit_order_id');
        });

        // a winner's invoice points at its lot
        if (Schema::hasTable('service_orders')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('auction_lot_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_orders', 'auction_lot_id')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->dropIndex(['auction_lot_id']);
                $table->dropColumn('auction_lot_id');
            });
        }
        Schema::table('auction_bidders', fn (Blueprint $table) => $table->dropColumn('deposit_kept_at'));
        Schema::table('auction_lots', function (Blueprint $table) {
            $table->dropIndex(['invoice_order_id']);
            $table->dropColumn(['invoice_order_id', 'payment_due_at', 'invoice_reminded_at', 'payment_overdue_at', 'handed_over_at', 'payout_amount', 'payout_paid_at', 'payout_reference']);
        });
        Schema::table('auctions', fn (Blueprint $table) => $table->dropColumn(['en_payment_note', 'ar_payment_note']));
    }
};

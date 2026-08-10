<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Affiliate commission hold period (slice 1 of the clawback policy).
 *
 * Commission used to be payable the instant a referred sub-order completed,
 * with no reversal path anywhere — so a refunded order left withdrawable cash
 * booked against a sale that had been undone (proven on the preview: RM5.00
 * before and RM5.00 after a full refund).
 *
 * The fix is a hold, not a clawback: a commission is `pending` until the return
 * window plus a buffer has passed, and a refund arriving inside that window
 * just reduces it. Nobody is ever billed for money they already saw as theirs.
 *
 * ⚠ EXISTING ROWS ARE LEFT `confirmed` ON PURPOSE. They were payable under the
 * old rules and some may already be inside a requested payout; demoting them to
 * `pending` would retroactively take balance off creators who did nothing wrong.
 * The hold applies from here forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_referrals', function (Blueprint $table) {
            // When the hold expires. Null = legacy row, already payable.
            $table->timestamp('locks_at')->nullable()->after('status');

            // How much of commission_sen has been voided by a refund. Payable is
            // always (commission_sen - reversed_sen). Kept as a separate column
            // rather than decrementing commission_sen so the original booking
            // stays readable — a creator asking "why is this RM2 not RM5" can be
            // answered from the row instead of from the audit log.
            $table->unsignedBigInteger('reversed_sen')->default(0)->after('commission_sen');

            // The lock job scans pending rows by due date.
            $table->index(['status', 'locks_at']);
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_referrals', function (Blueprint $table) {
            $table->dropIndex(['status', 'locks_at']);
            $table->dropColumn(['locks_at', 'reversed_sen']);
        });
    }
};

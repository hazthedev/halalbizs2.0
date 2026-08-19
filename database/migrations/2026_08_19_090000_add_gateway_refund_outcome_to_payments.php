<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Did the gateway actually move the money, or does a human still owe a portal
 * refund?
 *
 * `PaymentGateway::refund()` has always returned a bool and its own docblock
 * says "false → caller falls back to manual / store credit" — but RefundService
 * discarded it and wrote `refunded_sen` + `refunded_at` either way. With no
 * `services.ipay88.refund_url` configured (i.e. always, today) every online
 * refund records as refunded while the cash has not moved and someone still
 * owes the portal action. Nothing on the payment separated *the API returned
 * it* from *a human still must*.
 *
 * NULLABLE deliberately — three states, and the null one is not a gap:
 *   null  — no gateway refund was attempted. COD (the ledger adjustment IS the
 *           refund) or no cash share to return. Nothing is owed.
 *   true  — the gateway confirmed it. Done.
 *   false — the gateway did not. A human owes a refund in the merchant portal.
 *
 * A boolean-with-default cannot say the first of those, and defaulting to false
 * would flag every COD refund as owing a portal action that does not exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('gateway_refund_ok')->nullable()->after('refunded_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('gateway_refund_ok');
        });
    }
};

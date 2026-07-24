<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-sub-order refunded total, in sen. Refunds are issued per sub-order but the
 * only refund tracking lived on the order-level payment (`refunded_sen`), so a
 * repeated call had nothing to cap against and double-reversed. This column makes
 * each sub-order's cumulative refund the source of truth for idempotency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('refunded_sen')->default(0)->after('total_sen');
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn('refunded_sen');
        });
    }
};

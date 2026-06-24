<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1.1 — full voucher stacking. `funded_by` attributes who absorbs a voucher's
 * cost (platform vs seller) for ledger/reporting; `shipping_subsidy_sen` records
 * the shipping a free-shipping voucher waived on each sub-order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('funded_by')->nullable(); // platform | seller (derived from scope when null)
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_subsidy_sen')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropColumn('funded_by'));
        Schema::table('sub_orders', fn (Blueprint $table) => $table->dropColumn('shipping_subsidy_sen'));
    }
};

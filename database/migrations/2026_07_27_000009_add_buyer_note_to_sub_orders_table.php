<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AL-L2 (audit 2026-07-27): the buyer-facing checkout note (per store) was
 * collected in the UI and passed into CheckoutService::place() but silently
 * discarded — the transaction closure never captured it. This gives it a
 * home on the sub-order it belongs to (there is a TODO in
 * resources/views/livewire/seller/orders/detail.blade.php:86 already
 * expecting this column).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->string('buyer_note', 500)->nullable()->after('shop_discount_sen');
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn('buyer_note');
        });
    }
};

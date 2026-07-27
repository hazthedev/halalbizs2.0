<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AL-M4 (audit 2026-07-27): placeOrder had no server-side idempotency guard
 * — only client-side wire:loading.attr="disabled" — so two concurrent
 * scripted submits for the same cart could both pass the stock check and
 * produce two orders. CheckoutService now locks the buyer's cart row FIRST
 * (before variants/flash/group-buy/vouchers/wallet) and stamps which order
 * a cart was last turned into, so a duplicate submit that loses the race can
 * detect and return the order the winner just placed instead of creating a
 * second one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->foreignId('checkout_lock_order_id')->nullable()->after('user_id')
                ->constrained('orders')->nullOnDelete();
            $table->timestamp('checkout_locked_at')->nullable()->after('checkout_lock_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checkout_lock_order_id');
            $table->dropColumn('checkout_locked_at');
        });
    }
};

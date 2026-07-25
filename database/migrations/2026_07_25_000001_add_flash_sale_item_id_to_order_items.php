<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link an order line back to the flash-sale allocation it consumed, so the
 * allocation can be released when the order is cancelled and a buyer's flash
 * purchases can be counted across orders (per-buyer-limit was per-order only).
 * Mirrors the existing group_buy_id linkage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('flash_sale_item_id')->nullable()->after('group_buy_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flash_sale_item_id');
        });
    }
};

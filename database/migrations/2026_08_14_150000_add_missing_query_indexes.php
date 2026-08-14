<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit M-22, M-23, M-24: three indexes the hottest queries in the app were
 * filesorting without.
 *
 * Each name is given explicitly. Laravel derives a name from the table plus
 * every column, and MySQL caps an identifier at 64 characters — this codebase
 * has already been bitten twice by that (the FK-name incident on the first real
 * MySQL deploy), so nothing here is left to the generator.
 */
return new class extends Migration
{
    public function up(): void
    {
        // M-22 · the seller order list filesorts the store's whole history on
        // every page load. status alone is indexed, which does not help an
        // ORDER BY created_at within one store.
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->index(['store_id', 'status', 'created_at'], 'sub_orders_store_status_created_idx');
        });

        // M-23 · the admin audit log full-scans and filesorts the
        // fastest-growing table in the schema. EXPLAIN on the live DB: type=ALL,
        // Using filesort.
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('created_at', 'activity_log_created_at_idx');
        });

        // M-24 · the storefront's price sort runs a filesorting correlated
        // subquery over variants. Only (product_id, sku) is indexed today.
        Schema::table('product_variants', function (Blueprint $table) {
            $table->index(['product_id', 'price_sen'], 'product_variants_product_price_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', fn (Blueprint $t) => $t->dropIndex('sub_orders_store_status_created_idx'));
        Schema::table('activity_log', fn (Blueprint $t) => $t->dropIndex('activity_log_created_at_idx'));
        Schema::table('product_variants', fn (Blueprint $t) => $t->dropIndex('product_variants_product_price_idx'));
    }
};

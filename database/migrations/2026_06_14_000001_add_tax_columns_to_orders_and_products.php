<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M0.1 — worldwide tax engine. Adds the tax money columns to the order
 * snapshots (orders/sub_orders/order_items) and a tax_class to products.
 * All amounts are integer sen; tax_rate_bp is the applied rate in basis points.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_total_sen')->default(0);
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_sen')->default(0);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_sen')->default(0);
            $table->unsignedInteger('tax_rate_bp')->default(0);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('tax_class')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('tax_total_sen'));
        Schema::table('sub_orders', fn (Blueprint $table) => $table->dropColumn('tax_sen'));
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['tax_sen', 'tax_rate_bp']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('tax_class'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1.4 — escrow visibility + abandoned-cart recovery + seller health.
 * carts.reminded_at gates one recovery nudge per idle cart; seller_health
 * stores the scheduled scorecard rollup (rates in basis points).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable();
        });

        Schema::create('seller_health', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('orders_counted')->default(0);
            $table->unsignedInteger('cancel_rate_bp')->default(0);   // cancelled / orders
            $table->unsignedInteger('return_rate_bp')->default(0);   // returned+refunded / orders
            $table->unsignedInteger('defect_rate_bp')->default(0);   // (cancel + return) / orders
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('carts', fn (Blueprint $table) => $table->dropColumn('reminded_at'));
        Schema::dropIfExists('seller_health');
    }
};

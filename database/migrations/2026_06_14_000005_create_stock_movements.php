<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M0.5 — inventory forensics. Every stock change writes an immutable movement
 * (checkout sale, cancel/return restock, manual adjust) with the resulting
 * balance, so oversell can be traced. Variants gain a reorder threshold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('type');                      // App\Enums\StockMovementType
            $table->integer('qty_delta');                // signed: −sale, +restock
            $table->unsignedInteger('balance_after');
            $table->string('reference')->nullable();     // order_no / sub_order_no / note
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('low_stock_threshold')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropColumn('low_stock_threshold'));
    }
};

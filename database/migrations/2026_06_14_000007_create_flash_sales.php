<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1.2 — flash sales. A time-boxed campaign holds per-variant deal lines with
 * a promo price, an allocation separate from normal stock, a per-buyer limit
 * and a live sold counter. Allocation is decremented in the same lock as
 * variant stock at checkout (Hard Rule 3). All money is integer sen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('flash_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained()->cascadeOnDelete()->index();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('promo_price_sen');
            $table->unsignedInteger('allocated_qty');
            $table->unsignedInteger('per_buyer_limit')->default(1);
            $table->unsignedInteger('sold_qty')->default(0);
            $table->timestamps();
            $table->unique(['flash_sale_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('flash_sales');
    }
};

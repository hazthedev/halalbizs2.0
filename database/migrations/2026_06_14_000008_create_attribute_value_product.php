<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1.3 — faceted search. Assigns specific attribute VALUES to a product (brand,
 * material, size …) so the storefront can facet on them. Category-attribute
 * already says which attributes apply to a category; this says which values a
 * product actually has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_value_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'attribute_value_id']);
            $table->index('attribute_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_value_product');
    }
};

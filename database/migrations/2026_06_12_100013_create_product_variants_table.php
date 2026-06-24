<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('options_label')->nullable();
            $table->json('option_value_ids')->nullable();
            $table->unsignedBigInteger('price_sen');
            $table->unsignedBigInteger('sale_price_sen')->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2.7 — product metafields. Curated trust/detail signals (halal cert, SIRIM,
 * ingredients, expiry …) as additive key/value rows. Optional everywhere: a
 * product with no metafields behaves exactly as before. One row per (product,
 * key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_metafields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key');                 // config('metafields.definitions') key
            $table->text('value');
            $table->timestamps();

            $table->unique(['product_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_metafields');
    }
};

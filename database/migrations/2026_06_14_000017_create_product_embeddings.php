<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2.3 — semantic + visual search. One vector row per product: a normalised
 * text embedding (name/description/category/metafields) and an optional image
 * embedding (colour histogram locally; a vision model in prod). Cosine ranking
 * runs over these. Additive: products without an embedding simply don't appear
 * in semantic results, and keyword search is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('text_vector');
            $table->json('image_vector')->nullable();
            $table->string('model')->default('local');
            $table->unsignedSmallInteger('dimensions');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_embeddings');
    }
};

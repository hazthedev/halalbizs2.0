<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete()->index();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete()->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedBigInteger('amount_sen');
            $table->string('status')->default('active'); // active | expired | cancelled
            $table->timestamps();

            $table->index(['status', 'ends_at']); // boosts:expire + scopeActive
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_boosts');
    }
};

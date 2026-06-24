<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('payment_method');
            $table->string('payment_status')->default('pending')->index();
            $table->json('shipping_address');
            $table->unsignedBigInteger('subtotal_sen');
            $table->unsignedBigInteger('shipping_total_sen');
            $table->unsignedBigInteger('discount_total_sen')->default(0);
            $table->unsignedBigInteger('grand_total_sen');
            $table->char('display_currency', 3)->default('MYR');
            $table->decimal('display_rate', 16, 8)->default(1);
            $table->timestamp('placed_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

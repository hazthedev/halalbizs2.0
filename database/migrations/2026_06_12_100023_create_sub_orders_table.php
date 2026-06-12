<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_orders', function (Blueprint $table) {
            $table->id();
            $table->string('sub_order_no')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete()->index();
            $table->foreignId('store_id')->constrained()->restrictOnDelete()->index();
            $table->string('status')->default('pending_payment')->index();
            $table->unsignedBigInteger('items_subtotal_sen');
            $table->unsignedBigInteger('shipping_fee_sen')->default(0);
            $table->unsignedBigInteger('shop_discount_sen')->default(0);
            $table->unsignedBigInteger('total_sen');
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedBigInteger('commission_sen')->nullable();
            $table->string('tracking_courier')->nullable();
            $table->string('tracking_no')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('auto_complete_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_orders');
    }
};

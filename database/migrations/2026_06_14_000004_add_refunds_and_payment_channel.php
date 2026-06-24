<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M0.4 — line-item refunds + payment channel. Payments record the chosen
 * channel/bank (FPX/wallet/card) and any refunded amount; returns can be
 * scoped to specific order items + quantities with a computed refund.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('channel')->nullable();          // iPay88 PaymentId / method code
            $table->string('bank_code')->nullable();        // FPX bank, when applicable
            $table->unsignedBigInteger('refunded_sen')->default(0);
            $table->timestamp('refunded_at')->nullable();
        });

        Schema::table('return_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('refund_sen')->default(0);
        });

        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete()->index();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('refund_sen');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
        Schema::table('return_requests', fn (Blueprint $table) => $table->dropColumn('refund_sen'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['channel', 'bank_code', 'refunded_sen', 'refunded_at']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2.5 — Affiliate / creator program. A creator enrols once for a share code;
 * referred orders are attributed at creation (orders.affiliate_id snapshot) and
 * a commission is booked when each referred sub-order completes. Commission is
 * integer sen (Hard Rule 1); attribution is checkout-safe (set by an Order
 * observer, not by CheckoutService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('active');           // App\Enums\AffiliateStatus
            $table->unsignedInteger('commission_rate_bp');         // basis points
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
        });

        Schema::create('affiliate_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('items_subtotal_sen');
            $table->unsignedBigInteger('commission_sen');
            $table->string('status')->default('confirmed');        // App\Enums\AffiliateReferralStatus
            $table->timestamp('created_at')->nullable();

            $table->index(['affiliate_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('affiliate_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_id');
        });
        Schema::dropIfExists('affiliate_referrals');
        Schema::dropIfExists('affiliates');
    }
};

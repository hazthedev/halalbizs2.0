<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2.8 — Subscribe-and-save / predictive replenishment. A buyer schedules a
 * recurring delivery of a variant at a standing discount. A scheduled processor
 * places each cycle's order through the existing checkout (synthetic line +
 * forced sub price); orders.subscription_id snapshots the link. Money is
 * integer sen and the discount is basis points (Hard Rule 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('address_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedInteger('interval_days');
            $table->unsignedInteger('discount_bp')->default(0);
            $table->string('payment_method');                  // App\Enums\PaymentMethod
            $table->string('status')->default('active');        // App\Enums\SubscriptionStatus
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_ordered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_run_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('affiliate_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
        });
        Schema::dropIfExists('subscriptions');
    }
};

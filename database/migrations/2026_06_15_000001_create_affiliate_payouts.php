<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2.5 follow-up — affiliate withdrawals. Confirmed commission can now be cashed
 * out: a creator requests a payout (bank details snapshotted), an admin pays it
 * out of band and marks it Paid, or rejects it. Available = confirmed earnings −
 * Σ(requested+paid) payouts. Integer sen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_sen');
            $table->string('status')->default('requested');   // App\Enums\AffiliatePayoutStatus
            $table->json('bank_snapshot')->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_payouts');
    }
};

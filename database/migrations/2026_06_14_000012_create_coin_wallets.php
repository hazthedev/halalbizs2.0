<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2.1 — Loyalty Coins. A per-buyer wallet with a signed FIFO-expiring
 * transaction ledger. `balance` is the cached spendable total kept in lock-step
 * with SUM(remaining) of the live earn lots; redemption value is integer sen
 * (Hard Rule 1). Orders snapshot the coin value redeemed at checkout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);          // spendable coins
            $table->unsignedBigInteger('lifetime_earned')->default(0);
            $table->date('last_checkin_on')->nullable();
            $table->unsignedInteger('checkin_streak')->default(0);
            $table->date('last_spin_on')->nullable();
            $table->timestamps();
        });

        Schema::create('coin_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coin_wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type');                                     // App\Enums\CoinTransactionType
            $table->bigInteger('amount');                               // signed coins
            $table->unsignedBigInteger('remaining')->default(0);        // FIFO lot remainder (credit rows)
            $table->unsignedBigInteger('sen')->nullable();              // sen value (redeem/refund rows)
            $table->timestamp('expires_at')->nullable();
            $table->nullableMorphs('reference');                        // order / sub_order / review
            $table->string('description')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['coin_wallet_id', 'type']);
            $table->index(['amount', 'remaining']);                     // FIFO live-lot scan
            $table->index('expires_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('coin_redemption_sen')->default(0)->after('discount_total_sen');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('coin_redemption_sen'));
        Schema::dropIfExists('coin_transactions');
        Schema::dropIfExists('coin_wallets');
    }
};

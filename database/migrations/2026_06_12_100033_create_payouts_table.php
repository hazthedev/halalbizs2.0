<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('payout_no')->unique();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_sen');
            $table->string('status')->default('requested')->index();
            $table->json('bank_snapshot');
            $table->timestamp('requested_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('store_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->bigInteger('amount_sen');
            $table->string('status')->default('available')->index();
            $table->string('description')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_ledger_entries');
        Schema::dropIfExists('payouts');
    }
};

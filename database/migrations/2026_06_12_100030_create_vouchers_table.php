<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('scope');
            $table->foreignId('store_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('type');
            $table->unsignedBigInteger('value_sen')->nullable();
            $table->decimal('percent', 5, 2)->nullable();
            $table->unsignedBigInteger('max_discount_sen')->nullable();
            $table->unsignedBigInteger('min_spend_sen')->default(0);
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['store_id', 'code']);
        });

        Schema::create('voucher_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('sub_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('discount_sen');
            $table->timestamp('created_at')->nullable();
            $table->index(['voucher_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_usages');
        Schema::dropIfExists('vouchers');
    }
};

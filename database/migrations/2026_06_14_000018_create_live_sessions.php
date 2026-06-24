<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2.4 — live-commerce shoppable shell. A seller hosts a session (video embed +
 * a rail of featured products + an optional pinned voucher). Buyers add to cart
 * through the UNCHANGED checkout — the room is pure presentation over existing
 * catalogue, vouchers and orders, so it can't affect the money path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status')->default('scheduled');     // App\Enums\LiveSessionStatus
            $table->string('video_url')->nullable();             // YouTube/FB live embed URL
            $table->string('voucher_code')->nullable();          // pinned voucher (existing engine)
            $table->foreignId('featured_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
        });

        Schema::create('live_session_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['live_session_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_session_products');
        Schema::dropIfExists('live_sessions');
    }
};

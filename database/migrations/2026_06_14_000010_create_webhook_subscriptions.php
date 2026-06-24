<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1.7 — outbound webhooks. A subscription POSTs signed payloads on order
 * lifecycle events (order.paid, sub_order.shipped/delivered/refunded …) to an
 * external URL. A null store_id is a platform-level subscription; a set store_id
 * receives only its own store's events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->string('secret');
            $table->json('events'); // list of subscribed event names
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_subscriptions');
    }
};

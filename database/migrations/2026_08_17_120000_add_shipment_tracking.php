<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->string('tracking_provider', 32)->nullable()->after('tracking_no');
            $table->string('tracking_provider_id', 100)->nullable()->after('tracking_provider');
            $table->string('tracking_status', 32)->nullable()->after('tracking_provider_id');
            $table->text('tracking_url')->nullable()->after('tracking_status');
            $table->timestamp('tracking_last_event_at')->nullable()->after('tracking_url');

            $table->unique(['tracking_provider', 'tracking_provider_id']);
            $table->index('tracking_no');
        });

        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('external_id', 191);
            $table->string('status', 32);
            $table->string('raw_status', 100)->nullable();
            $table->text('message');
            $table->string('location')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['provider', 'external_id']);
            $table->index(['sub_order_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropUnique(['tracking_provider', 'tracking_provider_id']);
            $table->dropIndex(['tracking_no']);
            $table->dropColumn([
                'tracking_provider',
                'tracking_provider_id',
                'tracking_status',
                'tracking_url',
                'tracking_last_event_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller service ratings: an optional, separate star score a buyer gives the
 * SELLER (service, packing, communication) alongside the product review. One
 * per sub-order — stored on the first submitted review of that sub-order.
 * Stores cache the aggregate like the product-derived rating columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('seller_rating')->nullable()->after('comment');
            $table->text('seller_comment')->nullable()->after('seller_rating');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('service_rating_avg', 3, 2)->default(0)->after('rating_count');
            $table->unsignedInteger('service_rating_count')->default(0)->after('service_rating_avg');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['seller_rating', 'seller_comment']);
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['service_rating_avg', 'service_rating_count']);
        });
    }
};

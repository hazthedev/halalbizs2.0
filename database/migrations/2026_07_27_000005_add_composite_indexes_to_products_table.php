<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M6 (audit 2026-07-27): Listing.php filters status='live' then orders by
 * published_at (default) or sold_count (top sort) on the hottest storefront
 * page, but products only carries a single-column `status` index — MySQL
 * filesorts every catalog/category page under load. Composite indexes let
 * the query use the index for both the filter and the sort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['status', 'published_at']);
            $table->index(['status', 'sold_count']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['status', 'published_at']);
            $table->dropIndex(['status', 'sold_count']);
        });
    }
};

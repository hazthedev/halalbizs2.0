<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Halal certificate expiry watch — the last thing on /welcome still chipped
 * "In build". Two columns, each solving a problem the watch cannot work without.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('halal_certificates', function (Blueprint $table) {
            // The 60-day renewal nudge runs daily, so without a marker the
            // seller gets the same warning 60 times. Cleared when the term
            // moves, so a renewed-then-lapsing-again certificate nudges afresh.
            $table->timestamp('renewal_notified_at')->nullable()->after('export_paperwork');
        });

        Schema::table('products', function (Blueprint $table) {
            // Records that the WATCH delisted this product, so renewing the
            // certificate can restore exactly those and nothing else. Without
            // it, restoring means re-listing every delisted product of that
            // store — including ones the seller delisted deliberately, which
            // would put withdrawn stock back on sale.
            $table->timestamp('halal_delisted_at')->nullable()->after('status');
            $table->index('halal_delisted_at');
        });
    }

    public function down(): void
    {
        Schema::table('halal_certificates', function (Blueprint $table) {
            $table->dropColumn('renewal_notified_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['halal_delisted_at']);
            $table->dropColumn('halal_delisted_at');
        });
    }
};

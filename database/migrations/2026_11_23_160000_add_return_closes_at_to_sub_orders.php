<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HB-DEC-1123d: the return window a sub-order had on delivery day, fixed at
 * the delivered transition. NULL = delivered before this column existed;
 * those rows keep following the current setting (no backfill: it would
 * rewrite stored rows on live).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->timestamp('return_closes_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn('return_closes_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller money hold: sale / commission / cod_offset rows booked at completion
 * start `pending` and carry the instant they come off hold (delivered_at +
 * return window). NULL for every other row and for all rows booked before this
 * migration, which stay `available` — no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_ledger_entries', function (Blueprint $table) {
            $table->timestamp('available_at')->nullable()->after('status');
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::table('store_ledger_entries', function (Blueprint $table) {
            $table->dropIndex(['status', 'available_at']);
            $table->dropColumn('available_at');
        });
    }
};

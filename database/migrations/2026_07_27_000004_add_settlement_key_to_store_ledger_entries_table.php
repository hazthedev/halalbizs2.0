<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DB-level backstop for H2 (double-completion double ledger booking). A naive
 * unique(sub_order_id, type) would be WRONG — adjustment/refund/payout/cod_offset
 * entries legitimately repeat per sub-order (e.g. multiple partial refunds).
 * Instead: a nullable, unique settlement_key populated ONLY for the once-per-
 * sub-order settlement entries (Sale/Commission, e.g. "sale:{sub_order_id}" /
 * "commission:{sub_order_id}") and left NULL for every repeatable type — SQL
 * treats NULLs as distinct, so repeatable types stay unconstrained. Safe on
 * both SQLite (local/test) and MySQL (prod): a plain nullable unique string
 * column behaves identically on both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_ledger_entries', function (Blueprint $table) {
            $table->string('settlement_key')->nullable()->unique()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('store_ledger_entries', function (Blueprint $table) {
            $table->dropUnique(['settlement_key']);
            $table->dropColumn('settlement_key');
        });
    }
};

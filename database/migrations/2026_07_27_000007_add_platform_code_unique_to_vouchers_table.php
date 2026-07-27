<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M7 (audit 2026-07-27): vouchers.unique(['store_id', 'code']) does not
 * dedupe PLATFORM vouchers because store_id is NULL for them and SQL treats
 * NULLs as distinct — two platform vouchers can share a code and
 * VoucherService::lookup() then resolves whichever ->first() happens to
 * return. A partial/filtered unique index isn't portable across SQLite and
 * MySQL, so instead we add a generated column that is non-null only for
 * platform-scope vouchers and put a plain unique index on THAT — NULLs are
 * still excluded from uniqueness on both engines, but a real duplicate code
 * among platform vouchers now collides.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Resolve any existing duplicate platform-scope codes BEFORE the
        // unique index goes on, or adding it would fail outright. Non-
        // destructive: the oldest row (lowest id) keeps its code and stays
        // whatever active state it was in; every later row sharing that code
        // is deactivated and its code suffixed so it can never resolve (or
        // collide) again — no rows are deleted, the audit trail survives.
        $duplicateCodes = DB::table('vouchers')
            ->where('scope', 'platform')
            ->whereNull('store_id')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code');

        foreach ($duplicateCodes as $code) {
            $ids = DB::table('vouchers')
                ->where('scope', 'platform')
                ->whereNull('store_id')
                ->where('code', $code)
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids->slice(1) as $id) {
                DB::table('vouchers')->where('id', $id)->update([
                    'code' => $code.'-DUP-'.$id,
                    'is_active' => false,
                ]);
            }
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement("ALTER TABLE vouchers ADD COLUMN platform_code TEXT GENERATED ALWAYS AS (CASE WHEN scope = 'platform' THEN code ELSE NULL END) VIRTUAL");
        } else {
            DB::statement("ALTER TABLE vouchers ADD COLUMN platform_code VARCHAR(255) GENERATED ALWAYS AS (CASE WHEN scope = 'platform' THEN code ELSE NULL END) STORED");
        }

        Schema::table('vouchers', function (Blueprint $table) {
            $table->unique('platform_code');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropUnique(['platform_code']);
            $table->dropColumn('platform_code');
        });
    }
};

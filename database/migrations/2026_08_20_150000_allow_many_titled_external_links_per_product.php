<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace links become general "also available in" links.
 *
 * Three changes, one decision behind them (Haze, 2026-08-20): a seller may link
 * anywhere, not only to the four allow-listed marketplaces.
 *
 * 1. The `(product_id, platform)` unique index goes. It existed to stop a seller
 *    stacking five Shopee buttons on one product; the shopper now sees a single
 *    dropdown, so five entries cost one control instead of five, and a seller
 *    with two real Shopee listings had no way to show both.
 *
 * 2. `platform` becomes nullable. It is still the resolved allow-list key, and
 *    it is now also the whole of "verified": a row with a platform is on a host
 *    we recognise, a row without one is a link we do not vouch for. Nothing else
 *    stores that, so the two can never disagree — a platform removed from
 *    config/marketplaces.php un-verifies its links on the next page render,
 *    which is the behaviour we want.
 *
 * 3. `title` is what the shopper reads in the dropdown. Seller-supplied and
 *    required, because with arbitrary hosts there is no brand name to fall back
 *    on. Existing rows are backfilled from their platform's label below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_marketplace_links', function (Blueprint $table) {
            $table->dropUnique(['product_id', 'platform']);
            $table->string('title', 80)->default('')->after('platform');
        });

        // Backfill before the column is used: every existing row is on an
        // allow-listed host by construction, so its brand name is the honest
        // title. Done with a plain update rather than a model loop — the label
        // lives in config, and there are four of them.
        foreach ((array) config('marketplaces.platforms', []) as $key => $platform) {
            DB::table('product_marketplace_links')
                ->where('platform', $key)
                ->where('title', '')
                ->update(['title' => (string) ($platform['label'] ?? $key)]);
        }

        Schema::table('product_marketplace_links', function (Blueprint $table) {
            $table->string('platform')->nullable()->change();
        });
    }

    public function down(): void
    {
        // The unique index cannot be recreated over data the new shape allows,
        // so the rollback has to discard what will not fit: links on an
        // unrecognised host (no platform), then every duplicate beyond the
        // first for each platform. Destructive, and it is the only way back.
        DB::table('product_marketplace_links')->whereNull('platform')->delete();

        $firstPerPlatform = DB::table('product_marketplace_links')
            ->selectRaw('MIN(id) as id')
            ->groupBy('product_id', 'platform')
            ->pluck('id');

        DB::table('product_marketplace_links')->whereNotIn('id', $firstPerPlatform)->delete();

        Schema::table('product_marketplace_links', function (Blueprint $table) {
            $table->string('platform')->nullable(false)->change();
            $table->dropColumn('title');
            $table->unique(['product_id', 'platform']);
        });
    }
};

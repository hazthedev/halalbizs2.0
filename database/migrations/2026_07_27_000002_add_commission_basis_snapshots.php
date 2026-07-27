<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshots for the configurable commission basis (docs/09 §A).
 *
 * Hard rule #5 — snapshots are sacred. Once the basis is a setting someone can
 * flip, a historical commission is unexplainable without recording which rule
 * produced it and what the pre-discount price was at the time. `sub_orders`
 * already snapshots commission_rate for exactly this reason; these two columns
 * finish the job.
 *
 * - order_items.list_price_sen — the unit price BEFORE a flash-sale cut.
 *   unit_price_sen is what the buyer paid, which is already net of the flash
 *   promo, so GROSS has nothing to charge on without this.
 * - sub_orders.commission_basis — which rule was applied.
 *
 * Both are nullable and left NULL for existing rows ON PURPOSE. Backfilling
 * 'gross' would be a lie: the old code charged on items_subtotal_sen, which was
 * gross with respect to shop vouchers but net with respect to flash sales. NULL
 * honestly means "legacy: charged on items_subtotal as built".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('list_price_sen')->nullable()->after('unit_price_sen');
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->string('commission_basis')->nullable()->after('commission_sen');
        });

        // For rows that never saw a flash sale the paid price IS the list price,
        // so those can be filled in truthfully. Flash-sale lines stay NULL —
        // their pre-promo price was never recorded and must not be invented.
        DB::table('order_items')
            ->whereNull('flash_sale_item_id')
            ->update(['list_price_sen' => DB::raw('unit_price_sen')]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('list_price_sen');
        });

        Schema::table('sub_orders', function (Blueprint $table) {
            $table->dropColumn('commission_basis');
        });
    }
};

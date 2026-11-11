<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On a WHIP certificate only products WHIP lists get the badge. Ticked ones it
 * does not list wait here for an admin; ones an admin allowed are remembered so
 * the 4-hourly sync does not undo the Allow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('halal_certificates', function (Blueprint $table) {
            $table->json('whip_unmatched_product_ids')->nullable();
            $table->json('whip_allowed_product_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('halal_certificates', fn (Blueprint $t) => $t->dropColumn(['whip_unmatched_product_ids', 'whip_allowed_product_ids']));
    }
};

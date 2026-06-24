<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M0.3 — courier shipping. Sub-orders gain the booked-shipment fields (AWB,
 * label, chosen courier service); stores gain an origin postcode for live
 * rate quoting. shipping_mode already accepts a new 'easyparcel' value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->string('awb_no')->nullable();              // courier airway bill / consignment no.
            $table->string('shipping_label_url')->nullable();  // label PDF (stored or remote)
            $table->string('courier_service')->nullable();     // EasyParcel service id / courier code
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->string('shipping_origin_postcode')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', fn (Blueprint $table) => $table->dropColumn(['awb_no', 'shipping_label_url', 'courier_service']));
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn('shipping_origin_postcode'));
    }
};

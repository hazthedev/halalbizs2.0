<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('shipping_mode')->default('flat'); // flat | matrix
            $table->unsignedBigInteger('shipping_flat_fee_sen')->default(500);
            $table->json('shipping_matrix')->nullable(); // {state: fee_sen}
            $table->unsignedBigInteger('free_shipping_over_sen')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['shipping_mode', 'shipping_flat_fee_sen', 'shipping_matrix', 'free_shipping_over_sen']);
        });
    }
};

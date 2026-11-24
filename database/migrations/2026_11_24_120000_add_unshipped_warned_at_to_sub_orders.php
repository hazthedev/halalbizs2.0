<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAY-14 / HB-G143: when the seller got the "ship soon or it is cancelled"
 * warning, so the hourly job sends it once only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_orders', function (Blueprint $table) {
            $table->timestamp('unshipped_warned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sub_orders', fn (Blueprint $table) => $table->dropColumn('unshipped_warned_at'));
    }
};

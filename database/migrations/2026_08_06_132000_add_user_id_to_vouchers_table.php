<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voucher ownership. A spin prize is a single-use PERSONAL voucher, but the
 * row carried no owner — so the checkout picker listed it (code and all) to
 * every buyer, any of whom could burn the winner's quota. NULL keeps the
 * existing public behaviour; a set owner makes the row visible and redeemable
 * only to that user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};

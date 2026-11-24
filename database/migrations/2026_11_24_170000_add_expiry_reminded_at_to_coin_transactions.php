<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BIZ-04: a lot's 30-day expiry reminder is sent once; this stamp is the flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coin_transactions', function (Blueprint $table) {
            $table->timestamp('expiry_reminded_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('coin_transactions', fn (Blueprint $table) => $table->dropColumn('expiry_reminded_at'));
    }
};

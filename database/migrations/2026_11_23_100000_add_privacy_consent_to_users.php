<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDPA consent proof (LAW-02): when the person ticked the privacy notice at
 * sign-up and which notice version they saw. Nullable on purpose: accounts
 * made before this column existed have no proof and must NOT be backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('privacy_consented_at')->nullable();
            $table->string('privacy_notice_version', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['privacy_consented_at', 'privacy_notice_version']));
    }
};

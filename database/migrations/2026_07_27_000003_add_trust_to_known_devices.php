<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Trust this device for 30 days" — skip the 2FA code on a device the user has
 * already proven (Haze's ask 2026-07-27: keying a code EVERY admin login is
 * needless friction; the panel keeps 2FA, but not per-login).
 *
 * Trust rides the existing per-device known_devices row (already unique per
 * user + fingerprint). It is bound to BOTH a secret cookie token (something you
 * have) and the device fingerprint (UA + /24 IP), and only the token HASH is
 * stored — a leaked DB row cannot mint trust, and a stolen cookie replayed from
 * another network fails the fingerprint check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('known_devices', function (Blueprint $table) {
            $table->string('trust_token_hash', 64)->nullable()->after('label');
            $table->timestamp('trusted_until')->nullable()->after('trust_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('known_devices', function (Blueprint $table) {
            $table->dropColumn(['trust_token_hash', 'trusted_until']);
        });
    }
};

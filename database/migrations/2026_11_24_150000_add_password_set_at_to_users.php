<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HB-G226 (HB-DEC-1124e): when the user last chose a password. NULL = the
 * stored password is random and unknown (Google sign-up, admin invite), so
 * the bank form points them to Forgot password.
 *
 * Backfill: no google_id = signed up with a password (created_at); google_id
 * set = NULL. A password user who later linked Google sees the hint until
 * their next reset or change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable();
        });

        DB::table('users')->whereNull('google_id')->update(['password_set_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('password_set_at'));
    }
};

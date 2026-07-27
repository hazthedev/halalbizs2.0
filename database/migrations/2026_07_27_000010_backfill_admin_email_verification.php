<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AL-C5 (audit 2026-07-27): the admin route group now carries 'verified'.
 * Existing admin-role users predate that gate — they are already operating
 * the panel, so their address is trusted-in-practice; stamping it verified
 * here prevents the new middleware from locking out a live install (the
 * preview admin included). New staff are verified at invite time
 * (Staff::sendInvite() calls markEmailAsVerified()).
 */
return new class extends Migration
{
    public function up(): void
    {
        $adminUserIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'admin')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->pluck('model_has_roles.model_id');

        DB::table('users')
            ->whereIn('id', $adminUserIds)
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Irreversible by design: we cannot know which rows were null before.
    }
};

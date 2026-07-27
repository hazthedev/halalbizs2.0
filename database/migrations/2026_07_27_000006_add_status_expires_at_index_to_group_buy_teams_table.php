<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L6 (audit 2026-07-27): GroupBuyService::expireDueTeams() scans
 * group_buy_teams by status='forming' + expires_at, but the only index is
 * (group_buy_id, status) — unusable here since group_buy_id isn't part of
 * the query. Add the composite the cron actually filters on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_buy_teams', function (Blueprint $table) {
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('group_buy_teams', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
        });
    }
};

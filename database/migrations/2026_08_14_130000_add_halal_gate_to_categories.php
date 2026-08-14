<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit H-6, the enforcement half: gate food, badge the rest.
 *
 * NULLABLE, and that is the whole design. Null means "ask my parent", so the
 * rule is set once on Groceries & Pantry rather than on every leaf under it,
 * and a single node can override its branch either way. It mirrors
 * categories.commission_rate, which already resolves exactly like this via
 * Category::effectiveCommissionRate().
 *
 * With every row null on the day this lands, nothing is gated — the gate turns
 * on when someone sets it, not when the migration runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('requires_halal_certificate')->nullable()->after('commission_rate');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('requires_halal_certificate');
        });
    }
};

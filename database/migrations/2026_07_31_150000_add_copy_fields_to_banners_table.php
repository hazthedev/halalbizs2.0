<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A banner needs more than a label. `title` alone produced carousel slides that
 * named a category and sold nothing, so a banner now carries a headline (title),
 * a supporting line, and its own call to action — each translatable, like title.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->json('subtitle')->nullable()->after('title');
            $table->json('cta_label')->nullable()->after('subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->dropColumn(['subtitle', 'cta_label']);
        });
    }
};

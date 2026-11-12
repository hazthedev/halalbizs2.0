<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A named home layout that shows by itself between two times (CMS Tier 2, item 12). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->timestamps();
        });

        // null = the everyday layout. No data rewrite: every existing section stays everyday.
        Schema::table('home_sections', function (Blueprint $table) {
            $table->unsignedBigInteger('scheduled_layout_id')->nullable();
            $table->index('scheduled_layout_id', 'home_sections_layout_idx');
        });
    }

    public function down(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $table->dropIndex('home_sections_layout_idx');
            $table->dropColumn('scheduled_layout_id');
        });

        Schema::dropIfExists('scheduled_layouts');
    }
};

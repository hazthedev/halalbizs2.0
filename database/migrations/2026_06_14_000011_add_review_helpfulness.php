<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1.8 — review helpfulness. A denormalised counter on reviews plus a unique
 * per-user vote pivot so "X found this helpful" can't be inflated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->unsignedInteger('helpful_count')->default(0);
        });

        Schema::create('review_helpfuls', function (Blueprint $table) {
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['review_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_helpfuls');
        Schema::table('reviews', fn (Blueprint $table) => $table->dropColumn('helpful_count'));
    }
};

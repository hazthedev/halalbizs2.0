<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M2.6 — Group-buy / share-to-unlock. A seller offers a variant at a group price
 * unlocked when `target_size` shoppers join a team within the team window.
 * Refund-free model: joining is free, the deal price applies at checkout only
 * once the team is unlocked. order_items carries a non-price group_buy_id
 * snapshot (Hard Rule 5 — metadata, never alters the frozen price/name/variant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_buys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('group_price_sen');
            $table->unsignedInteger('target_size');            // members needed to unlock
            $table->unsignedInteger('team_window_hours')->default(24);
            $table->string('status')->default('active');       // App\Enums\GroupBuyStatus
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();

            $table->index(['product_variant_id', 'status']);
        });

        Schema::create('group_buy_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_buy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiator_id')->constrained('users')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('status')->default('forming');      // App\Enums\GroupBuyTeamStatus
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['group_buy_id', 'status']);
        });

        Schema::create('group_buy_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_buy_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('joined');       // App\Enums\GroupBuyMemberStatus
            $table->timestamps();

            $table->unique(['group_buy_team_id', 'user_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('group_buy_id')->nullable()->after('product_variant_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_buy_id');
        });
        Schema::dropIfExists('group_buy_members');
        Schema::dropIfExists('group_buy_teams');
        Schema::dropIfExists('group_buys');
    }
};

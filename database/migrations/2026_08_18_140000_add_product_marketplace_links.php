<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller-supplied outbound marketplace links (Shopee, Lazada, …). A child table
 * rather than a JSON column on `products` because we need the per-platform
 * unique constraint below — without it a seller can stack five Shopee buttons on
 * one product — and because "which products have links" stays an ordinary join.
 *
 * `platform` holds a resolved key from config('marketplaces.platforms'); it is
 * derived from the URL's host by MarketplaceLinkResolver and is never submitted
 * by the seller, so a link cannot be mislabelled as a platform it does not
 * belong to.
 *
 * `marketplace_links_always_visible` defaults FALSE deliberately. Links always
 * show while the marketplace is in listing-only mode — that is the point of the
 * feature — but when purchasing is switched back on, showing them routes a
 * paying shopper to a competitor's checkout. Defaulting off means restoring
 * purchasing cannot silently turn every product into an advert for Shopee; the
 * seller has to opt in per product. Same fail-safe reasoning as
 * `purchasing_enabled` defaulting ON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_marketplace_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('platform');  // config('marketplaces.platforms') key
            $table->text('url');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'platform']);
            $table->index(['product_id', 'position']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('marketplace_links_always_visible')->default(false)->after('cod_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('marketplace_links_always_visible');
        });

        Schema::dropIfExists('product_marketplace_links');
    }
};

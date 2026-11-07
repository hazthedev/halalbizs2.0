<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Support\Collection;

/** The buyer's saved products — heart toggles on cards and the wishlist page. */
class WishlistService
{
    /** Save or unsave a product; returns the new state (true = now wishlisted). */
    public function toggle(User $user, Product $product): bool
    {
        $deleted = Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->delete();

        if ($deleted > 0) {
            return false;
        }

        Wishlist::firstOrCreate(['user_id' => $user->id, 'product_id' => $product->id]);

        return true;
    }

    /** @return list<int> product ids in the user's wishlist — for heart states */
    public function ids(User $user): array
    {
        // Cast to int: every caller tests membership with in_array(..., true), and
        // a plucked column can arrive as a numeric STRING, in which case the
        // strict compare is always false and no heart ever fills. Proven on the
        // preview — the row is written and the toast fires, but the button is
        // byte-identical afterwards and stays that way through a full reload.
        return Wishlist::where('user_id', $user->id)
            ->pluck('product_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Wishlisted products, most recently saved first, with what a product card needs.
     *
     * @return Collection<int, Product>
     */
    public function products(User $user): Collection
    {
        $productIds = Wishlist::where('user_id', $user->id)
            ->latest()
            ->pluck('product_id');

        return Product::with(['halalCertificate', 'variants', 'store', 'media'])
            ->whereIn('id', $productIds)
            ->get()
            ->sortBy(fn (Product $product) => $productIds->search($product->id))
            ->values();
    }
}

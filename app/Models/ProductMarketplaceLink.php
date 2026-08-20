<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One outbound "also available in …" link for a product.
 *
 * `platform` is a config('marketplaces.platforms') key resolved from the URL's
 * host by MarketplaceLinkResolver — never seller input, so a link cannot claim
 * to be somewhere it is not. It is nullable, and its presence IS the verified
 * flag: a recognised host is a link we vouch for, anything else is one we merely
 * carry. There is no separate column, so the two cannot drift apart.
 *
 * `title` is what the shopper reads and is seller-supplied — with arbitrary
 * hosts allowed there is no brand name to fall back on.
 */
class ProductMarketplaceLink extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'platform', 'title', 'url', 'position'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Whether this link points at a marketplace we allow-list. Derived, never
     * stored — see the class docblock.
     */
    public function isVerified(): bool
    {
        return $this->platform !== null
            && config("marketplaces.platforms.{$this->platform}") !== null;
    }
}

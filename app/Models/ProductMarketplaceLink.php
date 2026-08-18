<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One outbound "also available on Shopee" link for a product.
 *
 * `platform` is a config('marketplaces.platforms') key resolved from the URL's
 * host by MarketplaceLinkResolver — never seller input. A row can therefore only
 * exist for an allow-listed host.
 */
class ProductMarketplaceLink extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'platform', 'url', 'position'];

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
     * Display name for the platform. Falls back to the stored key so a link
     * whose platform was later removed from config still renders as something
     * rather than an empty button.
     */
    public function label(): string
    {
        return (string) config("marketplaces.platforms.{$this->platform}.label", $this->platform);
    }
}

<?php

namespace App\Models;

use App\Enums\GroupBuyStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A seller's group-buy deal for one variant (M2.6): a discounted price unlocked
 * when `target_size` shoppers join a team within the window.
 */
class GroupBuy extends Model
{
    protected $fillable = [
        'store_id', 'product_id', 'product_variant_id', 'group_price_sen',
        'target_size', 'team_window_hours', 'status', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GroupBuyStatus::class,
            'group_price_sen' => 'integer',
            'target_size' => 'integer',
            'team_window_hours' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(GroupBuyTeam::class);
    }

    public function isLive(): bool
    {
        return $this->status === GroupBuyStatus::Active && now()->between($this->starts_at, $this->ends_at);
    }

    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('status', GroupBuyStatus::Active)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
}

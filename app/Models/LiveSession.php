<?php

namespace App\Models;

use App\Enums\LiveSessionStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A seller's live-commerce session (M2.4): a video embed plus a rail of
 * featured products and an optional pinned voucher. Read-only over the catalogue.
 */
class LiveSession extends Model
{
    protected $fillable = [
        'store_id', 'title', 'slug', 'status', 'video_url', 'voucher_code',
        'featured_product_id', 'scheduled_for', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LiveSessionStatus::class,
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'live_session_products')
            ->withPivot('position')
            ->orderBy('position')
            ->withTimestamps();
    }

    public function featuredProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'featured_product_id');
    }

    public function isLive(): bool
    {
        return $this->status === LiveSessionStatus::Live;
    }

    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('status', LiveSessionStatus::Live);
    }
}

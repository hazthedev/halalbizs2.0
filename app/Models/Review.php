<?php

namespace App\Models;

use App\Observers\ReviewObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[ObservedBy([ReviewObserver::class])]
class Review extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'order_item_id', 'product_id', 'store_id', 'user_id',
        'rating', 'comment', 'seller_rating', 'seller_comment',
        'seller_reply', 'seller_replied_at', 'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'seller_rating' => 'integer',
            'seller_replied_at' => 'datetime',
            'is_hidden' => 'boolean',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_hidden', false);
    }

    /** First name in full, surname masked to its initial: "Nurul Aina" → "Nurul A." */
    public function reviewerDisplayName(): string
    {
        $name = trim((string) $this->user?->name);

        if ($name === '') {
            return __('A buyer');
        }

        $parts = preg_split('/\s+/u', $name) ?: [$name];

        if (count($parts) === 1) {
            return $parts[0];
        }

        return $parts[0].' '.mb_strtoupper(mb_substr((string) end($parts), 0, 1)).'.';
    }

    /** Seller replies are editable for 24h after first posting, then locked. */
    public function replyLocked(): bool
    {
        return $this->seller_replied_at !== null && $this->seller_replied_at->lte(now()->subDay());
    }
}

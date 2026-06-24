<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's standing subscribe-and-save schedule for one variant (M2.8).
 */
class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'product_variant_id', 'address_id', 'qty', 'interval_days',
        'discount_bp', 'payment_method', 'status', 'next_run_at', 'last_ordered_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'status' => SubscriptionStatus::class,
            'qty' => 'integer',
            'interval_days' => 'integer',
            'discount_bp' => 'integer',
            'next_run_at' => 'datetime',
            'last_ordered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    #[Scope]
    protected function due(Builder $query): void
    {
        $query->where('status', SubscriptionStatus::Active)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }
}

<?php

namespace App\Models;

use App\Enums\AffiliateReferralStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A booked affiliate commission for one completed referred sub-order (M2.5).
 * One per sub-order (unique) so completion can never double-pay.
 */
class AffiliateReferral extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'affiliate_id', 'sub_order_id', 'buyer_id', 'items_subtotal_sen', 'commission_sen', 'status', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AffiliateReferralStatus::class,
            'items_subtotal_sen' => 'integer',
            'commission_sen' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}

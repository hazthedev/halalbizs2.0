<?php

namespace App\Models;

use App\Enums\AffiliatePayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A creator's affiliate withdrawal request (M2.5 follow-up).
 */
class AffiliatePayout extends Model
{
    protected $fillable = [
        'affiliate_id', 'amount_sen', 'status', 'bank_snapshot', 'reference', 'requested_at', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AffiliatePayoutStatus::class,
            'amount_sen' => 'integer',
            'bank_snapshot' => 'array',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}

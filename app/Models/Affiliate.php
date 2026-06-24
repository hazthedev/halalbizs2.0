<?php

namespace App\Models;

use App\Enums\AffiliateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A creator enrolled in the affiliate program (M2.5). Owns a unique share code;
 * earns commission on referred sub-orders that complete.
 */
class Affiliate extends Model
{
    protected $fillable = ['user_id', 'code', 'status', 'commission_rate_bp', 'clicks'];

    protected function casts(): array
    {
        return [
            'status' => AffiliateStatus::class,
            'commission_rate_bp' => 'integer',
            'clicks' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function isActive(): bool
    {
        return $this->status === AffiliateStatus::Active;
    }
}

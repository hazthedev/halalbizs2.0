<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Scheduled seller scorecard (M1.4). Rates are integer basis points. */
class SellerHealth extends Model
{
    protected $table = 'seller_health';

    protected $fillable = [
        'store_id', 'orders_counted', 'cancel_rate_bp', 'return_rate_bp', 'defect_rate_bp', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'orders_counted' => 'integer',
            'cancel_rate_bp' => 'integer',
            'return_rate_bp' => 'integer',
            'defect_rate_bp' => 'integer',
            'computed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** A defect rate at/above this is "at risk" (admin gate / seller nudge). */
    public function isAtRisk(): bool
    {
        return $this->defect_rate_bp >= 1000; // 10%
    }
}

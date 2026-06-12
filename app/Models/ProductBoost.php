<?php

namespace App\Models;

use App\Enums\BoostStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A paid placement window for one product (Phase-4). The fee is charged
 * up-front from the store's available ledger balance via
 * LedgerService::chargeBoost — this row only records the window. Cancelling
 * stops the placement immediately; the remaining days are NOT refunded (v1).
 */
class ProductBoost extends Model
{
    protected $fillable = ['product_id', 'store_id', 'starts_at', 'ends_at', 'amount_sen', 'status'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'amount_sen' => 'integer',
            'status' => BoostStatus::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** Currently buying placement: active status AND now inside the window. */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', BoostStatus::Active)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }
}

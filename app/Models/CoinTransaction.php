<?php

namespace App\Models;

use App\Enums\CoinTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One coin ledger entry (M2.1). Credit rows carry a `remaining` lot balance and
 * an `expires_at`; debit rows are negative with `remaining` 0. `created_at` only
 * — entries are immutable history (we only ever decrement a lot's remaining).
 */
class CoinTransaction extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'coin_wallet_id', 'type', 'amount', 'remaining', 'sen', 'expires_at',
        'reference_type', 'reference_id', 'description', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CoinTransactionType::class,
            'amount' => 'integer',
            'remaining' => 'integer',
            'sen' => 'integer',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CoinWallet::class, 'coin_wallet_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}

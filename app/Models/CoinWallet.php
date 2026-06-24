<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A buyer's Loyalty Coins wallet (M2.1). `balance` is the authoritative
 * spendable total, mutated only under lockForUpdate alongside a matching
 * coin_transactions row (see CoinService) so it never drifts from the ledger.
 */
class CoinWallet extends Model
{
    protected $fillable = [
        'user_id', 'balance', 'lifetime_earned', 'last_checkin_on', 'checkin_streak', 'last_spin_on',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'lifetime_earned' => 'integer',
            'checkin_streak' => 'integer',
            'last_checkin_on' => 'date',
            'last_spin_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CoinTransaction::class);
    }
}

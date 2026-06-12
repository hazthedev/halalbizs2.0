<?php

namespace App\Models;

use App\Enums\VoucherScope;
use App\Enums\VoucherType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'scope', 'store_id', 'code', 'type', 'value_sen', 'percent', 'max_discount_sen',
        'min_spend_sen', 'quota', 'per_user_limit', 'used_count', 'starts_at', 'ends_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'scope' => VoucherScope::class,
            'type' => VoucherType::class,
            'value_sen' => 'integer',
            'percent' => 'decimal:2',
            'max_discount_sen' => 'integer',
            'min_spend_sen' => 'integer',
            'quota' => 'integer',
            'per_user_limit' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    public function isWithinWindow(): bool
    {
        return now()->between($this->starts_at, $this->ends_at);
    }

    public function hasQuotaRemaining(): bool
    {
        return $this->quota === null || $this->used_count < $this->quota;
    }

    public function isRedeemableBy(User $user, int $cartSubtotalSen): bool
    {
        if (! $this->is_active || ! $this->isWithinWindow() || ! $this->hasQuotaRemaining()) {
            return false;
        }

        if ($cartSubtotalSen < $this->min_spend_sen) {
            return false;
        }

        $userUsages = $this->usages()->where('user_id', $user->id)->count();

        return $userUsages < $this->per_user_limit;
    }

    /** Discount for a given subtotal, in sen. */
    public function discountSenFor(int $subtotalSen): int
    {
        return match ($this->type) {
            VoucherType::Fixed => min($this->value_sen, $subtotalSen),
            VoucherType::Percent => min(
                intdiv($subtotalSen * (int) round((float) $this->percent * 100), 10000),
                $this->max_discount_sen ?? PHP_INT_MAX,
            ),
            VoucherType::FreeShipping => 0,
        };
    }
}

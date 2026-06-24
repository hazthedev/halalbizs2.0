<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_no', 'user_id', 'affiliate_id', 'subscription_id', 'payment_method', 'payment_status', 'shipping_address',
        'subtotal_sen', 'shipping_total_sen', 'discount_total_sen', 'coin_redemption_sen', 'tax_total_sen', 'grand_total_sen',
        'display_currency', 'display_rate', 'placed_at', 'paid_at', 'expires_at',
        'einvoice_requested',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'shipping_address' => 'array',
            'subtotal_sen' => 'integer',
            'shipping_total_sen' => 'integer',
            'discount_total_sen' => 'integer',
            'coin_redemption_sen' => 'integer',
            'tax_total_sen' => 'integer',
            'grand_total_sen' => 'integer',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'einvoice_requested' => 'boolean',
        ];
    }

    public static function generateOrderNo(): string
    {
        do {
            $no = 'MP'.now()->format('ym').strtoupper(Str::random(6));
        } while (static::where('order_no', $no)->exists());

        return $no;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isAwaitingPayment(): bool
    {
        return $this->payment_method === PaymentMethod::Ipay88
            && $this->payment_status === PaymentStatus::Pending
            && ($this->expires_at === null || now()->lt($this->expires_at));
    }
}

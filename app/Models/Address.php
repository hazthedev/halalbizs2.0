<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'label', 'recipient_name', 'phone', 'line1', 'line2',
        'postcode', 'city', 'state', 'country', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Address $address) {
            if ($address->is_default) {
                static::where('user_id', $address->user_id)
                    ->whereKeyNot($address->getKey())
                    ->update(['is_default' => false]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Snapshot shape stored on orders.shipping_address.
     */
    public function toSnapshot(): array
    {
        return $this->only([
            'recipient_name', 'phone', 'line1', 'line2', 'postcode', 'city', 'state', 'country',
        ]);
    }
}

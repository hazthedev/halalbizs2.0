<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Short-lived one-time code (email 2FA, phone verification). Only the
 * bcrypt hash is stored — the plain code exists in transit only.
 */
class OtpCode extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'purpose',
        'code_hash',
        'expires_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

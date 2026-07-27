<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A device (user agent + /24 IP block) a user has logged in from before.
 * Logins from unseen devices trigger NewDeviceLoginNotification.
 */
class KnownDevice extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint',
        'label',
        'trust_token_hash',
        'trusted_until',
        'last_seen_at',
    ];

    protected $hidden = [
        'trust_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'trusted_until' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function trustIsLive(): bool
    {
        return $this->trust_token_hash !== null
            && $this->trusted_until !== null
            && $this->trusted_until->isFuture();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

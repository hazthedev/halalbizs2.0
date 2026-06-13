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
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

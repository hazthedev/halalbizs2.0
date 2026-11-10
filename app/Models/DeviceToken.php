<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An FCM registration token for one installed copy of the mobile app.
 */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'last_seen_at'];

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

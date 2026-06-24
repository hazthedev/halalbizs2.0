<?php

namespace App\Models;

use App\Enums\GroupBuyTeamStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A recruiting team for a group-buy deal (M2.6). Unlocks when its joined member
 * count reaches the deal's target_size before expires_at.
 */
class GroupBuyTeam extends Model
{
    protected $fillable = [
        'group_buy_id', 'initiator_id', 'code', 'status', 'expires_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GroupBuyTeamStatus::class,
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function groupBuy(): BelongsTo
    {
        return $this->belongsTo(GroupBuy::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupBuyMember::class);
    }

    public function isForming(): bool
    {
        return $this->status === GroupBuyTeamStatus::Forming && $this->expires_at->isFuture();
    }

    public function isUnlocked(): bool
    {
        return $this->status === GroupBuyTeamStatus::Unlocked;
    }
}

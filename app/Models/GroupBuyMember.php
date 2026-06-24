<?php

namespace App\Models;

use App\Enums\GroupBuyMemberStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shopper's membership in a group-buy team (M2.6). One per (team, user).
 */
class GroupBuyMember extends Model
{
    protected $fillable = ['group_buy_team_id', 'user_id', 'sub_order_id', 'status'];

    protected function casts(): array
    {
        return [
            'status' => GroupBuyMemberStatus::class,
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(GroupBuyTeam::class, 'group_buy_team_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }
}

<?php

namespace App\Services;

use App\Enums\GroupBuyMemberStatus;
use App\Enums\GroupBuyStatus;
use App\Enums\GroupBuyTeamStatus;
use App\Exceptions\CheckoutException;
use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use App\Models\GroupBuyTeam;
use App\Models\SubOrder;
use App\Models\User;
use App\Notifications\GroupBuyUnlockedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Group-buy / share-to-unlock (M2.6). Refund-free: joining a team is free, the
 * team unlocks when target_size members join, and the group price applies at
 * checkout only for members of an UNLOCKED, unexpired team. All headcount and
 * redemption mutations happen under a team/member lockForUpdate (Hard Rule 3),
 * extending the canonical checkout lock order to
 * variants → flash → vouchers → wallet → group-buy.
 */
class GroupBuyService
{
    public function enabled(): bool
    {
        return (bool) config('groupbuy.enabled', true);
    }

    /** The live deal for a variant, if any (PDP). */
    public function liveDealForVariant(int $variantId): ?GroupBuy
    {
        if (! $this->enabled()) {
            return null;
        }

        return GroupBuy::query()->live()->where('product_variant_id', $variantId)->latest('id')->first();
    }

    /** Start a new team for a deal and join the initiator. */
    public function startTeam(User $user, GroupBuy $deal): GroupBuyTeam
    {
        if (! $deal->isLive()) {
            throw new CheckoutException(__('This group-buy deal is not available right now.'));
        }

        return DB::transaction(function () use ($user, $deal) {
            $team = GroupBuyTeam::create([
                'group_buy_id' => $deal->id,
                'initiator_id' => $user->id,
                'code' => $this->uniqueCode(),
                'status' => GroupBuyTeamStatus::Forming,
                'expires_at' => now()->addHours(max(1, $deal->team_window_hours)),
            ]);

            $locked = GroupBuyTeam::whereKey($team->id)->lockForUpdate()->first();
            $this->addMemberLocked($locked, $user);

            return $locked->fresh();
        });
    }

    /** Join an existing forming team (idempotent for an existing member). */
    public function joinTeam(User $user, GroupBuyTeam $team): GroupBuyMember
    {
        return DB::transaction(function () use ($user, $team) {
            $locked = GroupBuyTeam::whereKey($team->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->isForming()) {
                throw new CheckoutException(__('This group can no longer be joined — start a new one.'));
            }

            return $this->addMemberLocked($locked, $user);
        });
    }

    /**
     * Lock + return the buyer's redeemable group-buy memberships keyed by
     * variant_id (unlocked team, deal active, not yet purchased). Call inside
     * the checkout transaction.
     *
     * @param  array<int, int>  $variantIds
     * @return Collection<int, GroupBuyMember>
     */
    public function lockRedeemableFor(User $user, array $variantIds): Collection
    {
        if (! $this->enabled() || $variantIds === []) {
            return collect();
        }

        $members = GroupBuyMember::query()
            ->select('group_buy_members.*')
            ->join('group_buy_teams', 'group_buy_teams.id', '=', 'group_buy_members.group_buy_team_id')
            ->join('group_buys', 'group_buys.id', '=', 'group_buy_teams.group_buy_id')
            ->where('group_buy_members.user_id', $user->id)
            ->where('group_buy_members.status', GroupBuyMemberStatus::Joined)
            ->where('group_buy_teams.status', GroupBuyTeamStatus::Unlocked)
            ->where('group_buys.status', GroupBuyStatus::Active)
            ->whereIn('group_buys.product_variant_id', $variantIds)
            ->orderByDesc('group_buys.id')
            ->lockForUpdate()
            ->get();

        $members->load('team.groupBuy');

        // One membership per variant (the most recent deal wins).
        return $members->unique(fn (GroupBuyMember $m) => $m->team->groupBuy->product_variant_id)
            ->keyBy(fn (GroupBuyMember $m) => $m->team->groupBuy->product_variant_id);
    }

    /** Mark a (locked) membership purchased, linking the sub-order. */
    public function markPurchased(GroupBuyMember $member, SubOrder $subOrder): void
    {
        $member->forceFill([
            'status' => GroupBuyMemberStatus::Purchased,
            'sub_order_id' => $subOrder->id,
        ])->save();
    }

    /** Expire forming teams whose window has closed (scheduled). */
    public function expireDueTeams(): int
    {
        return GroupBuyTeam::where('status', GroupBuyTeamStatus::Forming)
            ->where('expires_at', '<=', now())
            ->update(['status' => GroupBuyTeamStatus::Expired]);
    }

    /** @param  GroupBuyTeam  $team  MUST be locked by the caller */
    private function addMemberLocked(GroupBuyTeam $team, User $user): GroupBuyMember
    {
        $existing = $team->members()->where('user_id', $user->id)->first();

        if ($existing !== null) {
            return $existing; // idempotent
        }

        $member = $team->members()->create([
            'user_id' => $user->id,
            'status' => GroupBuyMemberStatus::Joined,
        ]);

        if ($team->members()->count() >= $team->groupBuy->target_size) {
            $team->forceFill([
                'status' => GroupBuyTeamStatus::Unlocked,
                'completed_at' => now(),
            ])->save();

            $this->notifyUnlocked($team);
        }

        return $member;
    }

    /** Tell every joined member their team is unlocked and ready to check out. */
    private function notifyUnlocked(GroupBuyTeam $team): void
    {
        $productName = $team->groupBuy->product?->getTranslation('name', 'en') ?? __('your item');

        foreach ($team->members()->with('user')->get() as $member) {
            $member->user?->notify(new GroupBuyUnlockedNotification($team->code, $productName));
        }
    }

    private function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (GroupBuyTeam::where('code', $code)->exists());

        return $code;
    }
}

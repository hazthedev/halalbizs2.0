<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreFollow;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Follow / unfollow a shop — one rule set for the web button and the mobile API. */
class StoreFollowService
{
    /** A seller cannot follow their own store; only approved stores can be followed. */
    public function canFollow(User $user, Store $store): bool
    {
        return $store->isApproved() && $store->user_id !== $user->id;
    }

    /** Idempotent: following twice is still one row. False when not allowed. */
    public function follow(User $user, Store $store): bool
    {
        if (! $this->canFollow($user, $store)) {
            return false;
        }

        StoreFollow::firstOrCreate(['user_id' => $user->id, 'store_id' => $store->id]);

        return true;
    }

    /** Idempotent; always allowed (a suspended store can still be dropped). */
    public function unfollow(User $user, Store $store): void
    {
        StoreFollow::where('user_id', $user->id)->where('store_id', $store->id)->delete();
    }

    public function isFollowing(?User $user, Store $store): bool
    {
        return $user !== null && StoreFollow::where('user_id', $user->id)->where('store_id', $store->id)->exists();
    }

    public function followersCount(Store $store): int
    {
        return StoreFollow::where('store_id', $store->id)->count();
    }

    /** Followed approved stores, most recently followed first. */
    public function followedStores(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Store::query()->approved()
            ->join('store_follows', 'store_follows.store_id', '=', 'stores.id')
            ->where('store_follows.user_id', $user->id)
            ->orderByDesc('store_follows.id')
            ->select('stores.*')
            ->with('media')
            ->paginate($perPage);
    }
}

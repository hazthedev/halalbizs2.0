<?php

namespace App\Livewire\Storefront;

use App\Models\Store;
use App\Services\StoreFollowService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Follow / Following toggle + public follower count, on the store page and the
 * PDP seller card. A guest tap goes to login (as the wishlist heart does).
 */
class FollowButton extends Component
{
    public Store $store;

    public function mount(Store $store): void
    {
        $this->store = $store;
    }

    public function toggle(): void
    {
        $user = auth()->user();

        if ($user === null) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $service = app(StoreFollowService::class);

        if (! $service->canFollow($user, $this->store) && ! $service->isFollowing($user, $this->store)) {
            $this->dispatch('toast', message: __('You cannot follow your own shop.'), type: 'error');

            return;
        }

        if (! RateLimiter::attempt('follow:'.$user->id, 30, fn () => true, 60)) {
            $this->dispatch('toast', message: __('Too many requests. Please try again shortly.'), type: 'error');

            return;
        }

        if ($service->isFollowing($user, $this->store)) {
            $service->unfollow($user, $this->store);
            $this->dispatch('toast', message: __('Unfollowed :shop', ['shop' => $this->store->name]));
        } else {
            $service->follow($user, $this->store);
            $this->dispatch('toast', message: __('Following :shop', ['shop' => $this->store->name]));
        }

        $this->dispatch('follows-updated');
    }

    public function render()
    {
        $service = app(StoreFollowService::class);
        $user = auth()->user();

        return view('livewire.storefront.follow-button', [
            'following' => $service->isFollowing($user, $this->store),
            'followers' => $service->followersCount($this->store),
            'own' => $user !== null && $user->id === $this->store->user_id,
        ]);
    }
}

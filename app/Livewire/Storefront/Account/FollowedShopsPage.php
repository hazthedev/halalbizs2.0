<?php

namespace App\Livewire\Storefront\Account;

use App\Models\Store;
use App\Services\StoreFollowService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.storefront')]
class FollowedShopsPage extends Component
{
    use WithPagination;

    public function unfollow(int $storeId): void
    {
        $store = Store::find($storeId);

        if ($store !== null) {
            app(StoreFollowService::class)->unfollow(auth()->user(), $store);
        }
    }

    #[On('follows-updated')]
    public function refreshFollows(): void
    {
        // Handling the event is enough — Livewire re-renders the list.
    }

    public function render()
    {
        return view('livewire.storefront.account.followed-shops-page', [
            'stores' => app(StoreFollowService::class)->followedStores(auth()->user()),
        ])->title(__('Followed shops'));
    }
}

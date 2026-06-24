<?php

namespace App\Livewire\Storefront\Account;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Buyer subscribe-and-save manager (M2.8): pause, resume or cancel standing
 * replenishments. Thin — actions route through SubscriptionService.
 */
#[Layout('layouts.storefront')]
class Subscriptions extends Component
{
    public function mount(): void
    {
        abort_unless(config('subscriptions.enabled', true), 404);
    }

    public function pause(int $id, SubscriptionService $subscriptions): void
    {
        if ($sub = $this->ownedSubscription($id)) {
            $subscriptions->pause($sub);
        }
    }

    public function resume(int $id, SubscriptionService $subscriptions): void
    {
        if ($sub = $this->ownedSubscription($id)) {
            $subscriptions->resume($sub);
        }
    }

    public function cancel(int $id, SubscriptionService $subscriptions): void
    {
        if ($sub = $this->ownedSubscription($id)) {
            $subscriptions->cancel($sub);
            $this->dispatch('toast', message: __('Subscription cancelled.'), type: 'success');
        }
    }

    private function ownedSubscription(int $id): ?Subscription
    {
        return Subscription::where('id', $id)->where('user_id', auth()->id())->first();
    }

    public function render(): View
    {
        $subscriptions = Subscription::where('user_id', auth()->id())
            ->with(['variant.product'])
            ->latest('id')
            ->get();

        return view('livewire.storefront.account.subscriptions', [
            'subscriptions' => $subscriptions,
        ])->title(__('My subscriptions'));
    }
}

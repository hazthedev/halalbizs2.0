<?php

namespace App\Livewire\Admin\Sellers;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Admin\Sellers\Concerns\ReviewsDocuments;
use App\Models\Store;
use App\Notifications\StoreSuspended;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Store detail (docs/08 §B) — stats, suspend/reinstate with reason,
 * per-store commission override, document re-verification, storefront link.
 * Status and commission changes are activity-logged via Store's LogsActivity.
 */
#[Layout('layouts.admin')]
class StoreDetail extends Component
{
    use ReviewsDocuments;

    public Store $store;

    public string $suspendReason = '';

    /** Percent, 0–100, nullable — null inherits the category/global rate. */
    public ?string $commissionRate = null;

    public function mount(Store $store): void
    {
        $this->store = $store;
        $this->commissionRate = $store->commission_rate;
    }

    public function suspend(): void
    {
        $this->validate([
            'suspendReason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'suspendReason.required' => __('Give a reason — it is emailed to the owner and kept on record.'),
            'suspendReason.min' => __('Give a reason — it is emailed to the owner and kept on record.'),
        ]);

        $this->store->update([
            'status' => StoreStatus::Suspended,
            'rejection_reason' => $this->suspendReason,
        ]);

        $this->store->user->notify(new StoreSuspended($this->store, $this->suspendReason));

        $this->suspendReason = '';
        $this->dispatch('toast', message: __('Store suspended — the owner has been notified.'));
    }

    public function reinstate(): void
    {
        $this->store->update([
            'status' => StoreStatus::Approved,
            'rejection_reason' => null,
        ]);

        $this->dispatch('toast', message: __('Store reinstated — it is live again.'));
    }

    public function saveCommission(): void
    {
        $this->validate([
            'commissionRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'commissionRate.numeric' => __('Enter the rate as a percentage between 0 and 100.'),
            'commissionRate.min' => __('Enter the rate as a percentage between 0 and 100.'),
            'commissionRate.max' => __('Enter the rate as a percentage between 0 and 100.'),
        ]);

        $rate = $this->commissionRate !== null && trim($this->commissionRate) !== ''
            ? $this->commissionRate
            : null;

        $this->store->update(['commission_rate' => $rate]);
        $this->commissionRate = $this->store->commission_rate;

        $this->dispatch('toast', message: $rate === null
            ? __('Override cleared — the store inherits the category/global rate.')
            : __('Commission override saved'));
    }

    protected function reviewableDocuments(): HasMany
    {
        return $this->store->documents();
    }

    public function render()
    {
        $this->store->loadMissing(['user', 'documents.media', 'media']);

        return view('livewire.admin.sellers.store-detail', [
            'liveProductsCount' => $this->store->products()->where('status', ProductStatus::Live)->count(),
            'subOrdersCount' => $this->store->subOrders()->count(),
            'gmvSen' => (int) $this->store->subOrders()->where('status', SubOrderStatus::Completed)->sum('total_sen'),
        ])->title($this->store->name);
    }
}

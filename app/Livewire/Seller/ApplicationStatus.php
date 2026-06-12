<?php

namespace App\Livewire\Seller;

use App\Enums\StoreStatus;
use App\Models\Store;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Application status screen (docs/07 §A1) — what pending/rejected/suspended
 * sellers see instead of the seller panel.
 */
#[Layout('layouts.storefront')]
class ApplicationStatus extends Component
{
    public function mount(): void
    {
        $store = auth()->user()->store;

        if ($store === null) {
            $this->redirectRoute('seller.apply', navigate: true);

            return;
        }

        if ($store->isApproved()) {
            $this->redirectRoute('seller.dashboard', navigate: true);
        }
    }

    /**
     * Re-apply after rejection: clear the rejected application (force delete —
     * the soft-deleted row would still hold the unique user_id + slug indexes)
     * and send the user back to a fresh form.
     */
    public function reapply(): void
    {
        /** @var Store $store */
        $store = auth()->user()->store;

        abort_unless($store->status === StoreStatus::Rejected, 403);

        // Delete documents one by one so medialibrary removes their files.
        $store->documents()->get()->each->delete();
        $store->forceDelete();

        $this->redirectRoute('seller.apply', navigate: true);
    }

    public function render()
    {
        return view('livewire.seller.application-status', [
            'store' => auth()->user()->store,
        ])->title(__('Seller application'));
    }
}

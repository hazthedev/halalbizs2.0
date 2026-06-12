<?php

namespace App\Livewire\Concerns;

use App\Models\Store;

/**
 * Store scoping for seller-panel components. EVERY query in /seller must
 * go through currentStore() — never trust ids from the client.
 */
trait CurrentStore
{
    protected function currentStore(): Store
    {
        return auth()->user()->store;
    }

    /** Abort unless the given model belongs to the current store. */
    protected function authorizeStore(int $storeId): void
    {
        abort_unless($storeId === $this->currentStore()->id, 403);
    }
}

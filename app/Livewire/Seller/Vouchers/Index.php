<?php

namespace App\Livewire\Seller\Vouchers;

use App\Enums\VoucherScope;
use App\Livewire\Concerns\CurrentStore;
use App\Models\Voucher;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * SHOP voucher CRUD (docs/09 §B) — scope shop, store_id = currentStore().
 * Codes are unique per store (DB unique store_id+code). At checkout these
 * apply through VoucherService with min spend on THIS store's subtotal and
 * the discount landing on the sub-order's shop_discount_sen.
 */
#[Layout('layouts.seller')]
class Index extends Component
{
    use CurrentStore;

    public function toggleActive(int $voucherId): void
    {
        $voucher = $this->shopVoucher($voucherId);
        $voucher->update(['is_active' => ! $voucher->is_active]);

        $this->dispatch('toast', message: $voucher->is_active ? __('Voucher enabled') : __('Voucher disabled'));
    }

    public function delete(int $voucherId): void
    {
        $voucher = $this->shopVoucher($voucherId);

        if ($voucher->used_count > 0) {
            $this->dispatch('toast', message: __('This voucher has been used — disable it instead of deleting.'), type: 'error');

            return;
        }

        $voucher->delete();

        $this->dispatch('toast', message: __('Voucher deleted'));
    }

    public function render()
    {
        return view('livewire.seller.vouchers.index', [
            'vouchers' => $this->currentStore()->vouchers()
                ->where('scope', VoucherScope::Shop)
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->get(),
        ])->title(__('Shop vouchers'));
    }

    /** Store-scoped fetch — a foreign voucher id 404s, never leaks. */
    private function shopVoucher(int $voucherId): Voucher
    {
        return Voucher::where('scope', VoucherScope::Shop)
            ->where('store_id', $this->currentStore()->id)
            ->findOrFail($voucherId);
    }
}

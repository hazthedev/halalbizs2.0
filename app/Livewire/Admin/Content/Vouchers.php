<?php

namespace App\Livewire\Admin\Content;

use App\Enums\VoucherScope;
use App\Models\Voucher;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Vouchers extends Component
{
    public function toggleActive(int $voucherId): void
    {
        $voucher = $this->platformVoucher($voucherId);
        $voucher->update(['is_active' => ! $voucher->is_active]);

        $this->dispatch('toast', message: $voucher->is_active ? __('Voucher enabled') : __('Voucher disabled'));
    }

    public function delete(int $voucherId): void
    {
        $voucher = $this->platformVoucher($voucherId);

        if ($voucher->used_count > 0) {
            $this->dispatch('toast', message: __('This voucher has been used — disable it instead of deleting.'), type: 'error');

            return;
        }

        $voucher->delete();

        $this->dispatch('toast', message: __('Voucher deleted'));
    }

    private function platformVoucher(int $voucherId): Voucher
    {
        // Same scoping on the write path: an admin must not be able to edit or
        // delete a buyer's won prize through this screen.
        return Voucher::where('scope', VoucherScope::Platform)->whereNull('user_id')->findOrFail($voucherId);
    }

    public function render()
    {
        return view('livewire.admin.content.vouchers', [
            // M-25: every spin-to-win prize is created with scope=Platform AND a
            // user_id (SpinService), so without this the screen listed every prize
            // anyone had ever won alongside the real platform vouchers — and it is
            // unpaginated. The buyer-facing side was scoped when user_id arrived;
            // the admin side was not.
            'vouchers' => Voucher::where('scope', VoucherScope::Platform)
                ->whereNull('user_id')
                ->orderByDesc('starts_at')
                ->orderByDesc('id')
                ->get(),
        ])->title(__('Platform vouchers'));
    }
}

<?php

namespace App\Livewire\Seller\Vouchers;

use App\Enums\VoucherScope;
use App\Livewire\Concerns\CurrentStore;
use App\Models\Voucher;
use App\Support\JsonSearch;
use App\Livewire\Concerns\WithTable;
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
    use CurrentStore, WithTable;

    public const PER_PAGE = 15;

    protected function tableFilters(): array
    {
        return [];
    }

    protected function tableSorts(): array
    {
        return ['code' => 'code', 'type' => 'type', 'value' => 'value_sen', 'min_spend' => 'min_spend_sen', 'window' => 'starts_at', 'used' => 'used_count', 'active' => 'is_active'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['starts_at' => 'desc'];
    }

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

    public function bulkEnable(): void
    {
        $this->bulkActive(true);
    }

    public function bulkDisable(): void
    {
        $this->bulkActive(false);
    }

    private function bulkActive(bool $active): void
    {
        $count = $this->selectedVouchers()->update(['is_active' => $active]);
        $this->clearSelection();

        $this->dispatch('toast', message: trans_choice('{1}:count voucher updated|[2,*]:count vouchers updated', $count, ['count' => $count]));
    }

    /** Unused vouchers only (same rule as the single Delete); used ones stay. */
    public function bulkDelete(): void
    {
        $count = $this->selectedVouchers()->where('used_count', 0)->delete();
        $this->clearSelection();
        $this->resetPage();

        $this->dispatch('toast', message: trans_choice('{1}:count voucher deleted|[2,*]:count vouchers deleted', $count, ['count' => $count]));
    }

    private function selectedVouchers()
    {
        return Voucher::where('scope', VoucherScope::Shop)
            ->where('store_id', $this->currentStore()->id)
            ->whereIn('id', array_map(intval(...), $this->selected));
    }

    public function render()
    {
        return view('livewire.seller.vouchers.index', [
            'vouchers' => $this->results(),
        ])->title(__('Shop vouchers'));
    }

    /** One query for the grid and for "select all on this page" (WithTable::updatedSelectPage). */
    private function results()
    {
        return $this->sortTable(Voucher::where('store_id', $this->currentStore()->id)
            ->where('scope', VoucherScope::Shop)
            ->when(trim($this->search) !== '', fn ($q) => $q->whereRaw(JsonSearch::clause('code'), [JsonSearch::pattern($this->search)])))
            ->paginate($this->tablePageSize());
    }

    /** Store-scoped fetch — a foreign voucher id 404s, never leaks. */
    private function shopVoucher(int $voucherId): Voucher
    {
        return Voucher::where('scope', VoucherScope::Shop)
            ->where('store_id', $this->currentStore()->id)
            ->findOrFail($voucherId);
    }
}

<?php

namespace App\Livewire\Admin\Content;

use App\Enums\VoucherScope;
use App\Livewire\Concerns\WithTable;
use App\Models\Voucher;
use App\Support\JsonSearch;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Vouchers extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

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
        $this->resetPage();

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
            'vouchers' => $this->results(),
        ])->title(__('Platform vouchers'));
    }

    private function results()
    {
        // M-25: every spin-to-win prize is created with scope=Platform AND a
        // user_id (SpinService), so without the whereNull this listed every prize
        // anyone had ever won alongside the real platform vouchers.
        return $this->sortTable(Voucher::where('scope', VoucherScope::Platform)
            ->whereNull('user_id')
            ->when(trim($this->search) !== '', fn ($q) => $q->whereRaw(JsonSearch::clause('code'), [JsonSearch::pattern($this->search)])))
            ->paginate($this->tablePageSize());
    }
}

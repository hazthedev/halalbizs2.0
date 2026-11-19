<?php

namespace App\Livewire\Admin\Sellers;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithTable;

/**
 * Stores management (docs/08 §B) — every decided (non-pending) store with
 * search by name/owner email and a status filter. Rows open the detail page.
 */
#[Layout('layouts.admin')]
class Stores extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

    #[Url(except: '')]
    public string $status = '';

    protected function tableFilters(): array
    {
        return ['status'];
    }

    protected function tableSorts(): array
    {
        return [
            'store' => 'name', 'state' => 'state', 'status' => 'status', 'rating' => 'rating_avg',
            'products' => 'live_products_count', 'commission' => 'commission_rate',
        ];
    }

    protected function tableDefaultOrder(): array
    {
        return ['created_at' => 'desc'];
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
    }

    public function render()
    {
        $stores = Store::query()
            ->where('status', '!=', StoreStatus::Pending)
            ->when($this->statusFilter() !== null, fn (Builder $query) => $query->where('status', $this->statusFilter()))
            ->when(trim($this->search) !== '', function (Builder $query) {
                $term = $this->likeTerm();

                $query->where(fn (Builder $query) => $query
                    ->where('name', 'like', $term)
                    ->orWhereHas('user', fn (Builder $query) => $query->where('email', 'like', $term)));
            })
            ->with(['user', 'media'])
            ->withCount(['products as live_products_count' => fn (Builder $query) => $query->where('status', ProductStatus::Live)])
        ;

        return view('livewire.admin.sellers.stores', [
            'stores' => $this->sortTable($stores)->paginate($this->tablePageSize()),
            'statusOptions' => [
                StoreStatus::Approved,
                StoreStatus::Suspended,
                StoreStatus::Rejected,
            ],
        ])->title(__('Stores'));
    }

    private function statusFilter(): ?StoreStatus
    {
        $status = StoreStatus::tryFrom($this->status);

        // Pending stores live in the applications queue, never here.
        return $status === StoreStatus::Pending ? null : $status;
    }
}

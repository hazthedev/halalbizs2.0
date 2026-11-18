<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Models\Store;
use App\Models\SubOrder;
use App\Support\DisplayTime;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithTable;

/**
 * Admin orders oversight (docs/08 §E) — every sub-order across the
 * marketplace in one dense datagrid. Filters: status, store, payment
 * method, date range; search by order_no / sub_order_no. The admin
 * powers live on the detail view.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithTable;

    public const PER_PAGE = 20;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $store = '';

    #[Url(except: '')]
    public string $method = '';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    protected function tableFilters(): array
    {
        return ['status', 'store', 'method', 'dateFrom', 'dateTo'];
    }

    protected function tableSorts(): array
    {
        return ['placed' => 'created_at', 'total' => 'total_sen', 'status' => 'status'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['created_at' => 'desc'];
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'store', 'method', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.admin.orders.index', [
            'subOrders' => $this->results(),
            'stores' => Store::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => SubOrderStatus::cases(),
            'methods' => PaymentMethod::cases(),
        ])->title(__('Orders'));
    }

    private function results()
    {
        $query = SubOrder::query()
            ->with(['order.user', 'store'])
            ->when(SubOrderStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when(ctype_digit($this->store), fn ($query) => $query->where('store_id', (int) $this->store))
            ->when(PaymentMethod::tryFrom($this->method), fn ($query, $method) => $query->whereHas('order', fn ($q) => $q->where('payment_method', $method)))
            ->when($this->validDate($this->dateFrom), fn ($query, $date) => $query->where('created_at', '>=', DisplayTime::dayStart($date)))
            ->when($this->validDate($this->dateTo), fn ($query, $date) => $query->where('created_at', '<=', DisplayTime::dayEnd($date)))
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);

                $query->where(fn ($q) => $q
                    ->where('sub_order_no', 'like', "%{$term}%")
                    ->orWhereHas('order', fn ($order) => $order->where('order_no', 'like', "%{$term}%")));
            })
;

        return $this->sortTable($query)->paginate($this->tablePageSize());

    }

    /** Date inputs arrive as Y-m-d from the browser; ignore anything else. */
    private function validDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }
}

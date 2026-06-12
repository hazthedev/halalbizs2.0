<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\PaymentMethod;
use App\Enums\SubOrderStatus;
use App\Models\Store;
use App\Models\SubOrder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin orders oversight (docs/08 §E) — every sub-order across the
 * marketplace in one dense datagrid. Filters: status, store, payment
 * method, date range; search by order_no / sub_order_no. The admin
 * powers live on the detail view.
 */
#[Layout('layouts.admin')]
class Index extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    #[Url(except: '')]
    public string $search = '';

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

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'store', 'method', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'store', 'method', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    public function render()
    {
        $subOrders = SubOrder::query()
            ->with(['order.user', 'store'])
            ->when(SubOrderStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->when(ctype_digit($this->store), fn ($query) => $query->where('store_id', (int) $this->store))
            ->when(PaymentMethod::tryFrom($this->method), fn ($query, $method) => $query->whereHas('order', fn ($q) => $q->where('payment_method', $method)))
            ->when($this->validDate($this->dateFrom), fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($this->validDate($this->dateTo), fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);

                $query->where(fn ($q) => $q
                    ->where('sub_order_no', 'like', "%{$term}%")
                    ->orWhereHas('order', fn ($order) => $order->where('order_no', 'like', "%{$term}%")));
            })
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return view('livewire.admin.orders.index', [
            'subOrders' => $subOrders,
            'stores' => Store::query()->orderBy('name')->pluck('name', 'id'),
            'statuses' => SubOrderStatus::cases(),
            'methods' => PaymentMethod::cases(),
        ])->title(__('Orders'));
    }

    /** Date inputs arrive as Y-m-d from the browser; ignore anything else. */
    private function validDate(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}

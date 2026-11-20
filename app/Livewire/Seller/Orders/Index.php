<?php

namespace App\Livewire\Seller\Orders;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Livewire\Seller\Orders\Concerns\ManagesShipment;
use App\Models\SubOrder;
use App\Services\SubOrderStatusService;
use App\Support\Csv;
use App\Support\RinggitInput;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use App\Livewire\Concerns\WithTable;
use App\Enums\PaymentMethod;
use App\Support\DisplayTime;
use App\Support\JsonSearch;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Seller order queue (docs/07 §B) — status tabs with live counts
 * (wire:poll.30s badge bump), store-scoped table, inline quick actions.
 * The Returns tab (docs/09 §D) surfaces sub-orders awaiting a response.
 */
#[Layout('layouts.seller')]
class Index extends Component
{
    use CurrentStore, ManagesShipment, WithTable;

    public const PER_PAGE = 15;

    /** Hours a new order may wait before the age indicator turns warn. */
    public const ACT_FAST_HOURS = 24;

    #[Url(except: 'new')]
    public string $tab = 'new';

    #[Url(except: '')]
    public string $method = '';

    #[Url(except: '')]
    public string $dateFrom = '';

    #[Url(except: '')]
    public string $dateTo = '';

    protected function tableFilters(): array
    {
        return ['tab', 'method', 'dateFrom', 'dateTo'];
    }

    protected function tableSorts(): array
    {
        return ['placed' => 'created_at', 'total' => 'total_sen'];
    }

    protected function tableDefaultOrder(): array
    {
        return ['created_at' => 'desc'];
    }

    public function bulkConfirmAndPack(): void
    {
        $ids = SubOrder::query()->where('store_id', $this->currentStore()->id)
            ->where('status', SubOrderStatus::Confirmed)->whereIn('id', $this->selectedIds())->pluck('id');

        foreach ($ids as $id) {
            $this->confirmAndPack($id);
        }

        $this->clearSelection();
    }

    /** @return array<string, SubOrderStatus> tab key → status */
    public static function tabs(): array
    {
        return [
            'new' => SubOrderStatus::Confirmed,
            'to_ship' => SubOrderStatus::Processing,
            'shipped' => SubOrderStatus::Shipped,
            'delivered' => SubOrderStatus::Delivered,
            'completed' => SubOrderStatus::Completed,
            'returns' => SubOrderStatus::ReturnRequested,
            'cancelled' => SubOrderStatus::Cancelled,
        ];
    }

    public function mount(): void
    {
        if (! array_key_exists($this->tab, self::tabs())) {
            $this->tab = 'new';
        }
    }

    public function updatedTab(string $value): void
    {
        if (! array_key_exists($value, self::tabs())) {
            $this->tab = 'new';
        }

        $this->resetPage();
    }

    /** New → To ship: confirmed → processing, only via the status service. */
    public function confirmAndPack(int $subOrderId): void
    {
        $subOrder = SubOrder::query()
            ->where('store_id', $this->currentStore()->id)
            ->findOrFail($subOrderId);

        $statusService = app(SubOrderStatusService::class);

        if (! $statusService->canTransition($subOrder, SubOrderStatus::Processing)) {
            return;
        }

        $statusService->transition($subOrder, SubOrderStatus::Processing, ActorType::Seller, auth()->id());

        $this->dispatch('toast', message: __(':no confirmed — pack it, then arrange shipment.', ['no' => $subOrder->sub_order_no]));
    }

    /** Download this store's orders as a CSV (M1.5). */
    public function exportCsv(): StreamedResponse
    {
        $store = $this->currentStore();

        $rows = SubOrder::query()
            ->where('store_id', $store->id)
            ->with('order')
            ->latest('created_at')->latest('id')->get()
            ->map(fn (SubOrder $subOrder) => [
                $subOrder->sub_order_no,
                $subOrder->created_at?->display()->toDateTimeString(),
                $subOrder->status->value,
                RinggitInput::fromSen($subOrder->items_subtotal_sen),
                RinggitInput::fromSen($subOrder->shipping_fee_sen),
                RinggitInput::fromSen($subOrder->tax_sen),
                RinggitInput::fromSen($subOrder->total_sen),
                $subOrder->order?->payment_method?->value,
            ]);

        return Csv::stream(
            "orders-{$store->slug}.csv",
            ['sub_order_no', 'placed_at', 'status', 'items_rm', 'shipping_rm', 'tax_rm', 'total_rm', 'payment'],
            $rows,
        );
    }

    public function render()
    {
        return view('livewire.seller.orders.index', [
            'subOrders' => $this->results(),
            'methods' => PaymentMethod::cases(),
            'counts' => $this->counts(),
            'tabLabels' => [
                'new' => __('New'),
                'to_ship' => __('To ship'),
                'shipped' => __('Shipped'),
                'delivered' => __('Delivered'),
                'completed' => __('Completed'),
                'returns' => __('Returns'),
                'cancelled' => __('Cancelled'),
            ],
            'couriers' => self::COURIERS,
            'actFastHours' => self::ACT_FAST_HOURS,
        ])->title(__('Orders'));
    }

    private function results()
    {
        $query = SubOrder::query()
            ->where('store_id', $this->currentStore()->id)
            ->where('status', self::tabs()[$this->tab] ?? SubOrderStatus::Confirmed)
            ->with('order.user')
            ->when($this->tab === 'returns', fn ($query) => $query->with('returnRequest'))
            ->withCount('items')
            ->when(PaymentMethod::tryFrom($this->method), fn ($query, $method) => $query->whereHas('order', fn ($q) => $q->where('payment_method', $method)))
            ->when($this->validDate($this->dateFrom), fn ($query, $date) => $query->where('created_at', '>=', DisplayTime::dayStart($date)))
            ->when($this->validDate($this->dateTo), fn ($query, $date) => $query->where('created_at', '<=', DisplayTime::dayEnd($date)))
            ->when(trim($this->search) !== '', function ($query) {
                $like = JsonSearch::pattern(trim($this->search));
                $query->where(fn ($q) => $q->where('sub_order_no', 'like', $like)
                    ->orWhereHas('order', fn ($q) => $q->where('order_no', 'like', $like)
                        ->orWhereHas('user', fn ($q) => $q->where('name', 'like', $like))));
            });

        return $this->sortTable($query)->paginate($this->tablePageSize());
    }

    private function validDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    /** @return array<string, int> tab key → count (store-scoped, one query) */
    private function counts(): array
    {
        $byStatus = SubOrder::query()
            ->where('store_id', $this->currentStore()->id)
            ->whereIn('status', array_map(fn (SubOrderStatus $status) => $status->value, array_values(self::tabs())))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(self::tabs())
            ->map(fn (SubOrderStatus $status) => (int) ($byStatus[$status->value] ?? 0))
            ->all();
    }
}

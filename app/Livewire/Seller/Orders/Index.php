<?php

namespace App\Livewire\Seller\Orders;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Livewire\Concerns\CurrentStore;
use App\Livewire\Seller\Orders\Concerns\ManagesShipment;
use App\Models\SubOrder;
use App\Services\SubOrderStatusService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Seller order queue (docs/07 §B) — status tabs with live counts
 * (wire:poll.30s badge bump), store-scoped table, inline quick actions.
 * Returns tab arrives with M8.
 */
#[Layout('layouts.seller')]
class Index extends Component
{
    use CurrentStore, ManagesShipment, WithPagination;

    public const PER_PAGE = 15;

    /** Hours a new order may wait before the age indicator turns warn. */
    public const ACT_FAST_HOURS = 24;

    #[Url(except: 'new')]
    public string $tab = 'new';

    /** @return array<string, SubOrderStatus> tab key → status */
    public static function tabs(): array
    {
        return [
            'new' => SubOrderStatus::Confirmed,
            'to_ship' => SubOrderStatus::Processing,
            'shipped' => SubOrderStatus::Shipped,
            'delivered' => SubOrderStatus::Delivered,
            'completed' => SubOrderStatus::Completed,
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

    public function render()
    {
        $subOrders = SubOrder::query()
            ->where('store_id', $this->currentStore()->id)
            ->where('status', self::tabs()[$this->tab])
            ->with('order.user')
            ->withCount('items')
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return view('livewire.seller.orders.index', [
            'subOrders' => $subOrders,
            'counts' => $this->counts(),
            'tabLabels' => [
                'new' => __('New'),
                'to_ship' => __('To ship'),
                'shipped' => __('Shipped'),
                'delivered' => __('Delivered'),
                'completed' => __('Completed'),
                'cancelled' => __('Cancelled'),
            ],
            'couriers' => self::COURIERS,
            'actFastHours' => self::ACT_FAST_HOURS,
        ])->title(__('Orders'));
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

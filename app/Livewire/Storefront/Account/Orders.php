<?php

namespace App\Livewire\Storefront\Account;

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Livewire\Concerns\InteractsWithCart;
use App\Models\Order;
use App\Models\SubOrder;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.storefront')]
class Orders extends Component
{
    use InteractsWithCart, WithPagination;

    /** Tab key => sub-order statuses. "to-pay" lists parent orders instead. */
    private const TAB_STATUSES = [
        'to-ship' => [SubOrderStatus::Confirmed, SubOrderStatus::Processing],
        'to-receive' => [SubOrderStatus::Shipped, SubOrderStatus::Delivered],
        'completed' => [SubOrderStatus::Completed],
        'cancelled' => [SubOrderStatus::Cancelled],
        'return-refund' => [SubOrderStatus::ReturnRequested, SubOrderStatus::Returned, SubOrderStatus::Refunded],
    ];

    #[Url(except: 'to-pay')]
    public string $tab = 'to-pay';

    public function mount(): void
    {
        if (! in_array($this->tab, $this->tabKeys(), true)) {
            $this->tab = 'to-pay';
        }
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, $this->tabKeys(), true)) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage();
    }

    /**
     * Cancel an entire unpaid iPay88 order — every sub-order is still
     * pending_payment, so this is the whole-order pre-payment cancel
     * (docs/06 §F: no partial cancel before payment).
     */
    public function cancelUnpaidOrder(int $orderId): void
    {
        $order = Order::with('subOrders')
            ->where('user_id', auth()->id())
            ->findOrFail($orderId);

        if (! $order->isAwaitingPayment()) {
            $this->dispatch('toast', message: __('This order can no longer be cancelled here.'), type: 'error');

            return;
        }

        $orderService = app(OrderService::class);

        foreach ($order->subOrders as $subOrder) {
            if ($subOrder->status === SubOrderStatus::PendingPayment) {
                $orderService->cancel($subOrder, ActorType::Buyer, auth()->id(), __('Cancelled before payment'));
            }
        }

        $this->dispatch('toast', message: __('Order cancelled — nothing was charged.'));
    }

    /** Buyer confirms receipt of a delivered sub-order → completed. */
    public function confirmReceived(int $subOrderId): void
    {
        $subOrder = $this->ownedSubOrder($subOrderId);

        if ($subOrder->status !== SubOrderStatus::Delivered) {
            $this->dispatch('toast', message: __('This order is not marked delivered yet.'), type: 'error');

            return;
        }

        app(OrderService::class)->confirmReceived($subOrder, auth()->id());

        $this->dispatch('toast', message: __('Order completed — enjoy your purchase!'));
    }

    /** Re-add the first item's variant to the cart (live data, not the snapshot). */
    public function buyAgain(int $subOrderId): void
    {
        $subOrder = $this->ownedSubOrder($subOrderId)->load('items');
        $variantId = $subOrder->items->first()?->product_variant_id;

        if ($variantId === null) {
            $this->dispatch('toast', message: __('This product is not available right now.'), type: 'error');

            return;
        }

        $this->addToCart($variantId);
    }

    public function render()
    {
        $tabIsToPay = $this->tab === 'to-pay';

        return view('livewire.storefront.account.orders', [
            'tabs' => $this->tabLabels(),
            'counts' => $this->counts(),
            'orders' => $tabIsToPay
                ? $this->awaitingPaymentQuery()->with('subOrders')->latest('placed_at')->paginate(10)
                : null,
            'subOrders' => $tabIsToPay
                ? null
                : $this->subOrderQuery()
                    ->whereIn('status', self::TAB_STATUSES[$this->tab])
                    ->with(['items.product.media', 'store', 'order'])
                    ->latest('id')
                    ->paginate(10),
        ])->title(__('Orders'));
    }

    /** @return array<string, string> */
    private function tabLabels(): array
    {
        return [
            'to-pay' => __('To Pay'),
            'to-ship' => __('To Ship'),
            'to-receive' => __('To Receive'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            'return-refund' => __('Return/Refund'),
        ];
    }

    /** @return list<string> */
    private function tabKeys(): array
    {
        return array_keys($this->tabLabels());
    }

    /** @return array<string, int> count chips per tab */
    private function counts(): array
    {
        $byStatus = $this->subOrderQuery()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = ['to-pay' => $this->awaitingPaymentQuery()->count()];

        foreach (self::TAB_STATUSES as $key => $statuses) {
            $counts[$key] = collect($statuses)->sum(fn (SubOrderStatus $status) => (int) ($byStatus[$status->value] ?? 0));
        }

        return $counts;
    }

    private function subOrderQuery(): Builder
    {
        return SubOrder::query()
            ->whereHas('order', fn (Builder $query) => $query->where('user_id', auth()->id()));
    }

    private function awaitingPaymentQuery(): Builder
    {
        return Order::query()
            ->where('user_id', auth()->id())
            ->where('payment_method', PaymentMethod::Ipay88)
            ->where('payment_status', PaymentStatus::Pending)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('subOrders', fn (Builder $query) => $query->where('status', SubOrderStatus::PendingPayment));
    }

    private function ownedSubOrder(int $subOrderId): SubOrder
    {
        return $this->subOrderQuery()->findOrFail($subOrderId);
    }
}

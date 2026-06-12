<?php

namespace App\Livewire\Storefront;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The "Order placed" moment (docs/06 §A) — route binds {order:order_no},
 * owner-only. Renders the snapshot the order carries, never live data.
 */
#[Layout('layouts.storefront')]
class CheckoutSuccess extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        abort_unless($order->user_id === auth()->id(), 403);

        $this->order = $order->load(['subOrders.store']);
    }

    public function render(): View
    {
        return view('livewire.storefront.checkout-success', [
            'awaitingPayment' => $this->order->payment_method === PaymentMethod::Ipay88
                && $this->order->payment_status === PaymentStatus::Pending,
        ])->title(__('Order placed'));
    }

    /** Status pill colors (design §6): pending → warn · completed → emerald · cancelled/refund → danger. */
    public function statusVariant(SubOrderStatus $status): string
    {
        return match ($status) {
            SubOrderStatus::PendingPayment => 'warn',
            SubOrderStatus::Completed => 'sale',
            SubOrderStatus::Cancelled,
            SubOrderStatus::ReturnRequested,
            SubOrderStatus::Returned,
            SubOrderStatus::Refunded => 'danger',
            default => 'neutral',
        };
    }
}

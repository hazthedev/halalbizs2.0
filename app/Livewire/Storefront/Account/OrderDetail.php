<?php

namespace App\Livewire\Storefront\Account;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Models\CancellationReason;
use App\Models\SubOrder;
use App\Services\OrderService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class OrderDetail extends Component
{
    public SubOrder $subOrder;

    public bool $cancelling = false;

    public ?int $cancelReasonId = null;

    /** Happy-path steps used to render muted "future" timeline rows. */
    private const HAPPY_PATH = [
        SubOrderStatus::Confirmed,
        SubOrderStatus::Processing,
        SubOrderStatus::Shipped,
        SubOrderStatus::Delivered,
        SubOrderStatus::Completed,
    ];

    public function mount(SubOrder $subOrder): void
    {
        abort_unless($subOrder->order->user_id === auth()->id(), 403);

        $this->subOrder = $subOrder;
        $this->loadSubOrder();
    }

    /** Buyer cancel — only while confirmed (pre-ship, docs/06 §E). */
    public function cancel(OrderService $orderService): void
    {
        if (! $this->canCancel()) {
            $this->dispatch('toast', message: __('This order can no longer be cancelled — the seller has started on it.'), type: 'error');

            return;
        }

        $this->validate(
            ['cancelReasonId' => ['required', Rule::exists('cancellation_reasons', 'id')->where('is_active', true)]],
            ['cancelReasonId.required' => __('Pick a reason so the seller knows why.')],
        );

        $reason = CancellationReason::query()->active()->findOrFail($this->cancelReasonId);

        $orderService->cancel($this->subOrder, ActorType::Buyer, auth()->id(), $reason->label);

        $this->reset('cancelling', 'cancelReasonId');
        $this->loadSubOrder();

        $this->dispatch('toast', message: __('Order cancelled — your items are back in stock.'));
    }

    /** Buyer confirms receipt of a delivered order → completed. */
    public function confirmReceived(OrderService $orderService): void
    {
        if ($this->subOrder->status !== SubOrderStatus::Delivered) {
            $this->dispatch('toast', message: __('This order is not marked delivered yet.'), type: 'error');

            return;
        }

        $orderService->confirmReceived($this->subOrder, auth()->id());

        $this->loadSubOrder();

        $this->dispatch('toast', message: __('Order completed — enjoy your purchase!'));
    }

    public function render()
    {
        return view('livewire.storefront.account.order-detail', [
            'histories' => $this->subOrder->statusHistories,
            'futureStatuses' => $this->futureStatuses(),
            'canCancel' => $this->canCancel(),
            'showInvoice' => $this->showInvoice(),
            'cancelReasons' => $this->cancelling ? CancellationReason::query()->active()->get() : collect(),
        ])->title($this->subOrder->sub_order_no);
    }

    private function canCancel(): bool
    {
        return $this->subOrder->status === SubOrderStatus::Confirmed;
    }

    /** Invoice exists from confirmed onwards (never for unpaid or cancelled orders). */
    private function showInvoice(): bool
    {
        return ! in_array($this->subOrder->status, [SubOrderStatus::PendingPayment, SubOrderStatus::Cancelled], true);
    }

    /** @return list<SubOrderStatus> remaining happy-path steps, muted in the timeline */
    private function futureStatuses(): array
    {
        $status = $this->subOrder->status;

        if ($status === SubOrderStatus::PendingPayment) {
            return self::HAPPY_PATH;
        }

        $index = array_search($status, self::HAPPY_PATH, true);

        return $index === false ? [] : array_slice(self::HAPPY_PATH, $index + 1);
    }

    private function loadSubOrder(): void
    {
        $this->subOrder->refresh()->load(['items.product.media', 'statusHistories', 'store', 'order.payment']);
    }
}

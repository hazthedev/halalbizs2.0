<?php

namespace App\Livewire\Admin\Orders;

use App\Enums\ActorType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Models\ReturnRequest;
use App\Services\LedgerService;
use App\Services\SubOrderStatusService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Admin returns queue (docs/09 §D) — disputed + auto-escalated requests
 * needing a decision, with visibility tabs for the rest of the lifecycle.
 *
 * Resolution lives here (not on the order detail):
 * - Refund buyer: iPay88 money moves in the merchant portal (reference
 *   recorded); COD refunds are a ledger fact only. Sub-order → refunded via
 *   the service; if the sub-order completed first, a signed adjustment
 *   reverses sale and commission exactly: −(items+shipping−discount)+commission.
 * - Side with seller: restore the sub-order (completed when completed_at is
 *   set, delivered otherwise) and reject the request.
 */
#[Layout('layouts.admin')]
class Returns extends Component
{
    use WithPagination;

    public const PER_PAGE = 20;

    #[Url(except: 'queue')]
    public string $tab = 'queue';

    /** Request id with the resolution panel open. */
    public ?int $resolvingId = null;

    public string $refundReference = '';

    /** @return list<string> */
    public static function tabs(): array
    {
        return ['queue', 'accepted', 'refunded', 'all'];
    }

    public function mount(): void
    {
        if (! in_array($this->tab, self::tabs(), true)) {
            $this->tab = 'queue';
        }
    }

    public function updatedTab(string $value): void
    {
        if (! in_array($value, self::tabs(), true)) {
            $this->tab = 'queue';
        }

        $this->closeResolve();
        $this->resetPage();
    }

    public function openResolve(int $requestId): void
    {
        $this->resolvingId = ReturnRequest::query()->findOrFail($requestId)->id;
        $this->refundReference = '';
        $this->resetValidation();
    }

    public function closeResolve(): void
    {
        $this->resolvingId = null;
        $this->refundReference = '';
        $this->resetValidation();
    }

    /**
     * Refund the buyer. In one transaction: sub-order → refunded via the
     * service, order payment_status → refunded once every sub-order is
     * refunded/cancelled, request closed, and — when the sub-order completed
     * before the refund — a ledger adjustment reversing sale and commission.
     */
    public function refundBuyer(): void
    {
        $request = ReturnRequest::query()
            ->with('subOrder.order')
            ->findOrFail((int) $this->resolvingId);

        $subOrder = $request->subOrder;

        if (! in_array($request->status, [ReturnStatus::Accepted, ReturnStatus::Disputed, ReturnStatus::Escalated], true)
            || ! app(SubOrderStatusService::class)->canTransition($subOrder, SubOrderStatus::Refunded)) {
            $this->dispatch('toast', message: __('This return can no longer be refunded from its current status.'), type: 'error');

            return;
        }

        $isOnline = $subOrder->order->payment_method === PaymentMethod::Ipay88;

        if ($isOnline) {
            $this->validate(
                ['refundReference' => ['required', 'string', 'min:3', 'max:100']],
                [],
                ['refundReference' => __('iPay88 portal reference')],
            );
        }

        $reference = trim($this->refundReference);

        DB::transaction(function () use ($request, $subOrder, $isOnline, $reference) {
            app(SubOrderStatusService::class)->transition(
                $subOrder,
                SubOrderStatus::Refunded,
                ActorType::Admin,
                auth()->id(),
                $isOnline
                    ? __('Refunded via iPay88 portal — ref :ref', ['ref' => $reference])
                    : __('COD refund recorded as a ledger adjustment'),
            );

            // Order-level payment status flips only once everything is settled.
            $order = $subOrder->order;
            $allSettled = $order->subOrders()
                ->whereNotIn('status', [SubOrderStatus::Refunded, SubOrderStatus::Cancelled])
                ->doesntExist();

            if ($allSettled) {
                $order->update(['payment_status' => PaymentStatus::Refunded]);
            }

            $request->update([
                'status' => ReturnStatus::Refunded,
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
            ]);

            // Completed-before-refund → reverse sale and commission exactly
            // (integer sen): −(items+shipping−shop_discount) + commission_sen.
            if ($subOrder->ledgerEntries()->where('type', LedgerEntryType::Sale)->exists()) {
                $saleSen = $subOrder->items_subtotal_sen + $subOrder->shipping_fee_sen - $subOrder->shop_discount_sen;

                app(LedgerService::class)->adjustment(
                    $subOrder->store,
                    -$saleSen + (int) $subOrder->commission_sen,
                    __('Refund :no', ['no' => $subOrder->sub_order_no]),
                    $subOrder,
                );
            }
        });

        $this->closeResolve();

        $this->dispatch('toast', message: __(':no refunded — the buyer has been notified.', ['no' => $subOrder->sub_order_no]));
    }

    /** Reject the return and restore the sub-order to where it was. */
    public function sideWithSeller(int $requestId): void
    {
        $request = ReturnRequest::query()->with('subOrder')->findOrFail($requestId);
        $subOrder = $request->subOrder;

        if (! in_array($request->status, [ReturnStatus::Requested, ReturnStatus::Disputed, ReturnStatus::Escalated], true)
            || $subOrder->status !== SubOrderStatus::ReturnRequested) {
            $this->dispatch('toast', message: __('This return can no longer be rejected from its current status.'), type: 'error');

            return;
        }

        $restoreTo = $subOrder->completed_at !== null ? SubOrderStatus::Completed : SubOrderStatus::Delivered;

        DB::transaction(function () use ($request, $subOrder, $restoreTo) {
            app(SubOrderStatusService::class)->transition(
                $subOrder,
                $restoreTo,
                ActorType::Admin,
                auth()->id(),
                __('Return rejected — sided with the seller'),
            );

            $request->update([
                'status' => ReturnStatus::Rejected,
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
            ]);
        });

        $this->closeResolve();

        $this->dispatch('toast', message: __('Return rejected — :no restored to :status.', ['no' => $subOrder->sub_order_no, 'status' => $restoreTo->label()]));
    }

    public function render()
    {
        $requests = ReturnRequest::query()
            ->with(['subOrder.order.user', 'subOrder.store', 'reason'])
            ->when($this->tab === 'queue', fn ($query) => $query->whereIn('status', [ReturnStatus::Disputed, ReturnStatus::Escalated]))
            ->when($this->tab === 'accepted', fn ($query) => $query->where('status', ReturnStatus::Accepted))
            ->when($this->tab === 'refunded', fn ($query) => $query->where('status', ReturnStatus::Refunded))
            ->latest('id')
            ->paginate(self::PER_PAGE);

        $resolving = $this->resolvingId !== null
            ? ReturnRequest::query()->with(['subOrder.order', 'subOrder.store', 'reason'])->find($this->resolvingId)
            : null;

        return view('livewire.admin.orders.returns', [
            'requests' => $requests,
            'resolving' => $resolving,
            'counts' => $this->counts(),
            'tabLabels' => [
                'queue' => __('Needs decision'),
                'accepted' => __('Accepted'),
                'refunded' => __('Refunded'),
                'all' => __('All'),
            ],
        ])->title(__('Returns'));
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $byStatus = ReturnRequest::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'queue' => (int) ($byStatus[ReturnStatus::Disputed->value] ?? 0) + (int) ($byStatus[ReturnStatus::Escalated->value] ?? 0),
            'accepted' => (int) ($byStatus[ReturnStatus::Accepted->value] ?? 0),
            'refunded' => (int) ($byStatus[ReturnStatus::Refunded->value] ?? 0),
            'all' => (int) $byStatus->sum(),
        ];
    }
}

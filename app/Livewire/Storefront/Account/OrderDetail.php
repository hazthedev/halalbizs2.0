<?php

namespace App\Livewire\Storefront\Account;

use App\Enums\ActorType;
use App\Enums\ReturnStatus;
use App\Enums\SubOrderStatus;
use App\Models\CancellationReason;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Models\SubOrder;
use App\Services\OrderService;
use App\Services\SubOrderStatusService;
use App\Settings\OrderSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.storefront')]
class OrderDetail extends Component
{
    use WithFileUploads;

    public SubOrder $subOrder;

    public bool $cancelling = false;

    public ?int $cancelReasonId = null;

    public bool $requestingReturn = false;

    public ?int $returnReasonId = null;

    public string $returnDescription = '';

    /** @var list<TemporaryUploadedFile> */
    public array $returnPhotos = [];

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

    /**
     * Buyer requests a return (docs/09 §D): create the request and move the
     * sub-order to return_requested in one transaction. The status service
     * fires the event that notifies the seller.
     */
    public function submitReturn(): void
    {
        if (! $this->canRequestReturn()) {
            $this->dispatch('toast', message: __('This order is no longer eligible for a return.'), type: 'error');

            return;
        }

        $this->validate(
            [
                'returnReasonId' => ['required', Rule::exists('return_reasons', 'id')->where('is_active', true)],
                'returnDescription' => ['nullable', 'string', 'max:1000'],
                'returnPhotos' => ['array', 'max:'.ReturnRequest::MAX_PHOTOS],
                'returnPhotos.*' => ['image', 'max:2048'],
            ],
            [
                'returnReasonId.required' => __('Pick a reason so the seller understands the problem.'),
                'returnPhotos.max' => __('You can attach up to :max photos.', ['max' => ReturnRequest::MAX_PHOTOS]),
            ],
        );

        $responseHours = app(OrderSettings::class)->return_seller_response_hours;

        DB::transaction(function () use ($responseHours) {
            $request = ReturnRequest::create([
                'sub_order_id' => $this->subOrder->id,
                'return_reason_id' => $this->returnReasonId,
                'description' => trim($this->returnDescription) !== '' ? trim($this->returnDescription) : null,
                'status' => ReturnStatus::Requested,
                'respond_by' => now()->addHours($responseHours),
            ]);

            foreach (array_slice($this->returnPhotos, 0, ReturnRequest::MAX_PHOTOS) as $photo) {
                $request->addMedia($photo->getRealPath())
                    ->usingFileName('return-'.$photo->getClientOriginalName())
                    ->toMediaCollection('photos');
            }

            app(SubOrderStatusService::class)->transition(
                $this->subOrder,
                SubOrderStatus::ReturnRequested,
                ActorType::Buyer,
                auth()->id(),
            );
        });

        $this->reset('requestingReturn', 'returnReasonId', 'returnDescription', 'returnPhotos');
        $this->loadSubOrder();

        $this->dispatch('toast', message: __('Return requested — the seller has :hours hours to respond.', ['hours' => $responseHours]));
    }

    public function render()
    {
        return view('livewire.storefront.account.order-detail', [
            'histories' => $this->subOrder->statusHistories,
            'futureStatuses' => $this->futureStatuses(),
            'canCancel' => $this->canCancel(),
            'showInvoice' => $this->showInvoice(),
            'cancelReasons' => $this->cancelling ? CancellationReason::query()->active()->get() : collect(),
            'canRequestReturn' => $this->canRequestReturn(),
            'returnReasons' => $this->requestingReturn ? ReturnReason::query()->active()->get() : collect(),
            'returnRequest' => $this->subOrder->returnRequest,
        ])->title($this->subOrder->sub_order_no);
    }

    private function canCancel(): bool
    {
        return $this->subOrder->status === SubOrderStatus::Confirmed;
    }

    /**
     * Delivered/completed, inside the return window, and no request yet
     * (one return request per sub-order — docs/09 §D).
     */
    private function canRequestReturn(): bool
    {
        return in_array($this->subOrder->status, [SubOrderStatus::Delivered, SubOrderStatus::Completed], true)
            && $this->subOrder->delivered_at !== null
            && now()->lte($this->subOrder->delivered_at->copy()->addDays(app(OrderSettings::class)->return_window_days))
            && $this->subOrder->returnRequest === null;
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
        $this->subOrder->refresh()->load([
            'items.product.media', 'statusHistories', 'store', 'order.payment',
            'returnRequest.reason', 'returnRequest.media',
        ]);
    }
}

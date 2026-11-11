<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActorType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Exceptions\InvalidTransitionException;
use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\SubOrderResource;
use App\Livewire\Storefront\Account\Orders;
use App\Models\Order;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Models\SubOrder;
use App\Services\OrderService;
use App\Services\ReturnService;
use App\Services\ReviewService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The buyer's orders (mobile plan §4): the web Orders tabs as `bucket`, one
 * sub-order per row, and the buyer actions over OrderService / ReturnService
 * / ReviewService. Every lookup is scoped to the caller's orders, so another
 * buyer's sub-order is a 404.
 */
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bucket = $request->validate(['bucket' => ['nullable', Rule::in(array_keys(Orders::tabLabels()))]])['bucket'] ?? null;

        $subOrders = $this->bucketQuery($request, $bucket)
            ->with(['items.product.media', 'items.review', 'store.media', 'order', 'returnRequest.reason'])
            ->latest('id')
            ->paginate(10);

        $subOrders->setCollection($subOrders->getCollection()->map(fn (SubOrder $subOrder) => new SubOrderResource($subOrder)));

        return ApiResponse::ok($subOrders, ['bucket' => $bucket]);
    }

    /** The web tabs: key, label and the caller's count per bucket. */
    public function buckets(Request $request): JsonResponse
    {
        $buckets = [];

        foreach (Orders::tabLabels() as $key => $label) {
            $buckets[] = ['key' => $key, 'label' => $label, 'count' => $this->bucketQuery($request, $key)->count()];
        }

        return ApiResponse::ok($buckets);
    }

    public function show(Request $request, int $subOrder): JsonResponse
    {
        return ApiResponse::ok($this->detail($this->owned($request, $subOrder)));
    }

    /**
     * Buyer cancel: a confirmed sub-order (OrderDetail::cancel), or — for an
     * unpaid online order — every pending_payment sub-order of the order at
     * once (Orders::cancelUnpaidOrder: no partial cancel before payment).
     */
    public function cancel(Request $request, int $subOrder, OrderService $orders): JsonResponse
    {
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;
        $row = $this->owned($request, $subOrder);

        try {
            if ($row->status === SubOrderStatus::PendingPayment && $row->order->isAwaitingPayment()) {
                $orders->cancelUnpaid($row->order, ActorType::Buyer, $request->user()->id, $reason ?? __('Cancelled before payment'));
            } else {
                $orders->cancel($row, ActorType::Buyer, $request->user()->id, $reason);
            }
        } catch (InvalidTransitionException $e) {
            return $this->invalidTransition();
        }

        return ApiResponse::ok($this->detail($row->fresh()));
    }

    public function confirmReceived(Request $request, int $subOrder, OrderService $orders): JsonResponse
    {
        $row = $this->owned($request, $subOrder);

        try {
            $orders->confirmReceived($row, $request->user()->id);
        } catch (InvalidTransitionException $e) {
            return $this->invalidTransition();
        }

        return ApiResponse::ok($this->detail($row->fresh()));
    }

    public function returnReasons(): JsonResponse
    {
        return ApiResponse::ok(ReturnReason::query()->active()->get()
            ->map(fn (ReturnReason $reason) => ['id' => $reason->id, 'label' => $reason->label])
            ->values()->all());
    }

    /** Multipart: reason_id, note?, photos[] (same limits as OrderDetail::submitReturn). */
    public function returnRequest(Request $request, int $subOrder, ReturnService $returns): JsonResponse
    {
        $data = $request->validate([
            'reason_id' => ['required', Rule::exists('return_reasons', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:'.ReturnRequest::MAX_PHOTOS],
            'photos.*' => ['image', 'max:2048'],
        ], [
            'photos.max' => __('You can attach up to :max photos.', ['max' => ReturnRequest::MAX_PHOTOS]),
        ]);

        $row = $this->owned($request, $subOrder);

        if (! $returns->canRequest($row)) {
            return ApiResponse::error('return_not_allowed', __('This order is no longer eligible for a return.'), 409);
        }

        $returnRequest = $returns->request(
            $row,
            $request->user(),
            ReturnReason::query()->findOrFail($data['reason_id']),
            $data['note'] ?? null,
            array_values($request->file('photos', [])),
        );

        return ApiResponse::ok([
            'id' => $returnRequest->id,
            'status' => $returnRequest->status->value,
            'reason' => $returnRequest->reason?->label,
            'created_at' => $returnRequest->created_at?->toIso8601String(),
        ], null, 201);
    }

    /** Multipart or JSON: items[i]{order_item_id, rating, body?, photos[]?}, seller_rating?, seller_comment? (ReviewOrder rules). */
    public function review(Request $request, int $subOrder, ReviewService $reviews): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.rating' => ['required', 'integer', 'between:1,5'],
            'items.*.body' => ['nullable', 'string', 'min:10', 'max:2000'],
            'items.*.photos' => ['nullable', 'array', 'max:5'],
            'items.*.photos.*' => ['image', 'max:4096'],
            'seller_rating' => ['nullable', 'integer', 'between:1,5'],
            'seller_comment' => ['nullable', 'string', 'max:500'],
        ], [
            'items.*.body.min' => __('Tell us a little more — at least 10 characters.'),
            'items.*.photos.max' => __('Up to 5 photos per review.'),
        ]);

        $row = $this->owned($request, $subOrder);
        $files = $request->file('items', []);

        $items = collect($data['items'])->map(fn (array $item, int $i) => [
            'order_item_id' => (int) $item['order_item_id'],
            'rating' => (int) $item['rating'],
            'body' => $item['body'] ?? null,
            'photos' => array_values($files[$i]['photos'] ?? []),
        ])->values()->all();

        $reviews->submit(
            $row,
            $request->user(),
            $items,
            isset($data['seller_rating']) ? (int) $data['seller_rating'] : null,
            $data['seller_comment'] ?? null,
        );

        return ApiResponse::ok($this->detail($row->fresh()), null, 201);
    }

    /** Payment state as the payments row says it (hard rule 4) plus a fresh hand-off URL while still payable. */
    public function payment(Request $request, string $orderNo): JsonResponse
    {
        $order = $request->user()->orders()->where('order_no', $orderNo)->firstOrFail();

        return ApiResponse::ok([
            'order_no' => $order->order_no,
            'method' => $order->payment_method->value,
            'status' => $order->payment_status->value,
            'paid' => $order->payment_status === PaymentStatus::Paid,
            'expires_at' => $order->expires_at?->toIso8601String(),
            'retry_url' => CheckoutController::paymentUrl($order),
            'total_sen' => $order->grand_total_sen,
            'sub_orders' => CheckoutController::subOrders($order),
        ]);
    }

    private function bucketQuery(Request $request, ?string $bucket): Builder
    {
        $query = SubOrder::query()->whereHas('order', fn (Builder $order) => $order->where('user_id', $request->user()->id));

        if ($bucket === null) {
            return $query;
        }

        if ($bucket === 'to-pay') {
            // Orders::awaitingPaymentQuery, at sub-order grain.
            return $query->where('status', SubOrderStatus::PendingPayment)
                ->whereHas('order', fn (Builder $order) => $order
                    ->where('payment_method', PaymentMethod::Ipay88)
                    ->where('payment_status', PaymentStatus::Pending)
                    ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())));
        }

        return $query->whereIn('status', Orders::TAB_STATUSES[$bucket]);
    }

    private function owned(Request $request, int $subOrder): SubOrder
    {
        return $this->bucketQuery($request, null)->findOrFail($subOrder);
    }

    private function detail(SubOrder $subOrder): SubOrderResource
    {
        $subOrder->load([
            'items.product.media', 'items.review', 'store.media', 'order', 'statusHistories', 'returnRequest.reason',
        ]);

        return new SubOrderResource($subOrder, detail: true);
    }

    private function invalidTransition(): JsonResponse
    {
        return ApiResponse::error('invalid_transition', __('This order can no longer be changed that way.'), 409);
    }
}

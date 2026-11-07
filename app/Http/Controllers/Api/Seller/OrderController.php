<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Http\Api\ApiResponse;
use App\Http\Resources\SellerOrderResource;
use App\Models\SubOrder;
use App\Services\OrderService;
use App\Services\SellerReturnService;
use App\Services\ShipmentService;
use App\Services\SubOrderStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The seller order queue and its fulfilment actions (mobile plan §4). Every
 * status change goes through the services (CLAUDE.md hard rule 2); a
 * refused transition is a 409 `invalid_transition`.
 */
class OrderController extends SellerController
{
    public const PER_PAGE = 15;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', 'nullable', Rule::enum(SubOrderStatus::class)]]);

        $subOrders = $this->currentStore($request)->subOrders()
            ->with(['order.user', 'items.product.media', 'returnRequest.reason'])
            ->when(($data['status'] ?? null) !== null, fn ($query) => $query->where('status', $data['status']))
            ->latest('created_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);

        return ApiResponse::ok(SellerOrderResource::collection($subOrders));
    }

    public function show(Request $request, int $subOrder): JsonResponse
    {
        return $this->detail($request, $subOrder);
    }

    /** @return array<int, string> */
    public function couriers(): JsonResponse
    {
        return ApiResponse::ok(ShipmentService::COURIERS);
    }

    /** confirmed → processing. */
    public function pack(Request $request, int $subOrder, SubOrderStatusService $status): JsonResponse
    {
        $row = $this->subOrder($request, $subOrder);

        return $this->attempt(function () use ($request, $row, $status) {
            $status->transition($row, SubOrderStatus::Processing, ActorType::Seller, $request->user()->id);

            return $this->detail($request, $row->id);
        });
    }

    /** processing → shipped, with the tracking write, EasyParcel and AfterShip hooks. */
    public function ship(Request $request, int $subOrder, ShipmentService $shipment): JsonResponse
    {
        $data = $request->validate([
            'courier' => ['required', 'string', Rule::in([...ShipmentService::COURIERS, 'other'])],
            'courier_other' => ['exclude_unless:courier,other', 'required', 'string', 'max:60'],
            'tracking_no' => ['required', 'string', 'min:6', 'max:64'],
        ], [], [
            'courier' => __('courier'),
            'courier_other' => __('courier name'),
            'tracking_no' => __('tracking number'),
        ]);

        $row = $this->subOrder($request, $subOrder);

        return $this->attempt(function () use ($request, $row, $shipment, $data) {
            $shipment->ship($row, $data['courier'], $data['courier_other'] ?? null, $data['tracking_no'], $request->user()->id);

            return $this->detail($request, $row->id);
        });
    }

    /** shipped → delivered (COD settles here, inside OrderService). */
    public function delivered(Request $request, int $subOrder, OrderService $orders): JsonResponse
    {
        $row = $this->subOrder($request, $subOrder);

        return $this->attempt(function () use ($request, $row, $orders) {
            $orders->markDelivered($row, ActorType::Seller, $request->user()->id);

            return $this->detail($request, $row->id);
        });
    }

    public function returnAccept(Request $request, int $subOrder, SellerReturnService $returns): JsonResponse
    {
        $row = $this->subOrder($request, $subOrder);

        return $this->attempt(function () use ($request, $row, $returns) {
            $returns->accept($row);

            return $this->detail($request, $row->id);
        });
    }

    public function returnDispute(Request $request, int $subOrder, SellerReturnService $returns): JsonResponse
    {
        $data = $request->validate(
            ['reason' => ['required', 'string', 'min:10', 'max:1000']],
            [],
            ['reason' => __('dispute reason')],
        );

        $row = $this->subOrder($request, $subOrder);

        return $this->attempt(function () use ($request, $row, $returns, $data) {
            $returns->dispute($row, $data['reason']);

            return $this->detail($request, $row->id);
        });
    }

    public function returnReceived(Request $request, int $subOrder, SellerReturnService $returns): JsonResponse
    {
        $row = $this->subOrder($request, $subOrder);

        return $this->attempt(function () use ($request, $row, $returns) {
            $returns->received($row, $request->user()->id);

            return $this->detail($request, $row->id);
        });
    }

    private function subOrder(Request $request, int $id): SubOrder
    {
        return $this->currentStore($request)->subOrders()->with('returnRequest')->findOrFail($id);
    }

    private function detail(Request $request, int $id): JsonResponse
    {
        $row = $this->currentStore($request)->subOrders()
            ->with(['order.user', 'items.product.media', 'returnRequest.reason', 'statusHistories'])
            ->findOrFail($id);

        return ApiResponse::ok(new SellerOrderResource($row));
    }
}

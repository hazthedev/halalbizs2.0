<?php

namespace App\Http\Controllers;

use App\Enums\ActorType;
use App\Enums\SubOrderStatus;
use App\Models\SubOrder;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * EasyParcel tracking webhook (developers.easyparcel.com). Token-gated, not
 * CSRF-gated (mirrors the iPay88 callback policy). Idempotent: only a
 * 'delivered' event advances a Shipped sub-order, and it goes through
 * OrderService::markDelivered so COD settlement + e-invoicing still fire.
 */
class EasyParcelWebhookController extends Controller
{
    public function tracking(Request $request, OrderService $orders)
    {
        $token = (string) config('shipping.easyparcel.webhook_token');

        if ($token === '' || ! hash_equals($token, (string) $request->input('token'))) {
            abort(401);
        }

        $awb = trim((string) ($request->input('awb_no') ?? $request->input('tracking_no') ?? ''));

        if ($awb === '') {
            return response('OK');
        }

        $subOrder = SubOrder::where('awb_no', $awb)->first();

        if ($subOrder === null) {
            Log::info('EasyParcel webhook for unknown AWB.', ['awb' => $awb]);

            return response('OK');
        }

        // Only a delivery event moves the order; in-transit events are informational.
        if (strtolower(trim((string) $request->input('status'))) === 'delivered'
            && $subOrder->status === SubOrderStatus::Shipped) {
            $orders->markDelivered($subOrder, ActorType::System);
        }

        return response('OK');
    }
}

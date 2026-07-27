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

        // Header first, POST body as a fallback — NEVER the query string
        // (AL-C15): $request->input() also reads query params, so a
        // `?token=` on the configured webhook URL would land verbatim in
        // web-server/CDN access logs and Referer headers. If EasyParcel is
        // currently configured with a `?token=` URL, switch it to send
        // `X-EasyParcel-Token` (or keep the token in the POST body) — this
        // gate no longer accepts it from the query string.
        $provided = (string) ($request->header('X-EasyParcel-Token') ?: $request->post('token', ''));

        if ($token === '' || ! hash_equals($token, $provided)) {
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

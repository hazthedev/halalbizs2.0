<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SubOrder;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Checkout over CheckoutService (mobile plan §4): quote() previews with the
 * same code place() charges with. ApiLocale has already put the app's
 * X-Display-Currency where place() reads it (session('display_currency')).
 */
class CheckoutController extends Controller
{
    public function quote(Request $request, CheckoutService $checkout): JsonResponse
    {
        $data = $this->validated($request, placing: false);
        $user = $request->user();

        $quote = $checkout->quote(
            $user,
            isset($data['address_id']) ? $user->addresses()->findOrFail($data['address_id']) : null,
            $data['shipping_code'] ?? null,
            $data['platform_code'] ?? null,
            $data['shop_code'] ?? null,
            (int) ($data['coins'] ?? 0),
            isset($data['payment_method']) ? PaymentMethod::from($data['payment_method']) : null,
        );

        return ApiResponse::ok($quote->toArray());
    }

    public function place(Request $request, CheckoutService $checkout): JsonResponse
    {
        $data = $this->validated($request, placing: true);
        $user = $request->user();
        $address = $user->addresses()->findOrFail($data['address_id']);

        // One key per checkout attempt, reused on retry: the same key replays
        // the order it placed (CheckoutService::place), however late the retry.
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey !== null && ! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $idempotencyKey)) {
            return ApiResponse::error('validation_failed', __('The Idempotency-Key header must be 8 to 64 letters, digits, - or _.'), 422,
                ['idempotency_key' => [__('Invalid Idempotency-Key header.')]]);
        }

        // A selected line the basket already marks unavailable must be named,
        // not silently dropped (CheckoutService::blockedLines) — keyed by variant_id.
        $blocked = $checkout->blockedLines($user);

        if ($blocked->isNotEmpty()) {
            return ApiResponse::error('checkout_failed', __('Some selected items can no longer be bought.'), 409,
                $blocked->mapWithKeys(fn (object $line) => [(string) $line->variant->id => [$line->reason]])->all());
        }

        $order = $checkout->place(
            $user,
            $address,
            PaymentMethod::from($data['payment_method']),
            $data['platform_code'] ?? null,
            $data['shop_code'] ?? null,
            array_map('strval', $data['seller_notes'] ?? []),
            $data['shipping_code'] ?? null,
            (int) ($data['coins'] ?? 0),
            idempotencyKey: $idempotencyKey,
        );

        $order->loadMissing('subOrders.store');

        return ApiResponse::ok([
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'payment_method' => $order->payment_method->value,
            'total_sen' => $order->grand_total_sen,
            'sub_orders' => $order->subOrders->map(fn (SubOrder $subOrder) => [
                'id' => $subOrder->id,
                'store' => ['id' => $subOrder->store->id, 'name' => $subOrder->store->name],
                'status' => $subOrder->status->value,
            ])->values()->all(),
            'payment_url' => self::paymentUrl($order),
            'expires_at' => $order->expires_at?->toIso8601String(),
        ], null, 201);
    }

    /** A fresh 15-minute signed hand-off URL while the order can still be paid online. */
    public static function paymentUrl(Order $order): ?string
    {
        return $order->isAwaitingPayment()
            ? URL::temporarySignedRoute('app.pay', now()->addMinutes(15), ['order' => $order->order_no])
            : null;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $placing): array
    {
        return $request->validate([
            'address_id' => [$placing ? 'required' : 'nullable', 'integer'],
            'payment_method' => [$placing ? 'required' : 'nullable', Rule::enum(PaymentMethod::class)],
            'shipping_code' => ['nullable', 'string', 'max:64'],
            'platform_code' => ['nullable', 'string', 'max:64'],
            'shop_code' => ['nullable', 'string', 'max:64'],
            'coins' => ['nullable', 'integer', 'min:0'],
            'seller_notes' => ['nullable', 'array'],
            'seller_notes.*' => ['nullable', 'string', 'max:500'],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\GatewayPaymentStatus;
use App\Jobs\ConfirmIpay88PaymentJob;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Ipay88Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Ipay88Controller extends Controller
{
    /**
     * Bridge page (§D1): auto-submitting POST form to the iPay88 entry URL.
     * Each payment attempt gets its own RefNo: order_no, order_no-2, …
     */
    public function pay(Request $request, Order $order, Ipay88Service $ipay88)
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($order->isAwaitingPayment(), 404);

        $payment = $order->payments()
            ->where('status', GatewayPaymentStatus::Pending)
            ->latest('id')
            ->first();

        if ($payment === null) {
            // New attempt after a failed/expired one (docs/06 §D5 decision).
            $attempt = $order->payments()->count() + 1;

            $payment = Payment::create([
                'order_id' => $order->id,
                'gateway' => $order->payment_method,
                'ref_no' => "{$order->order_no}-{$attempt}",
                'amount_sen' => $order->grand_total_sen,
                'currency' => 'MYR',
                'status' => GatewayPaymentStatus::Pending,
            ]);
        }

        $fields = $ipay88->entryFields($order, $payment);

        $payment->update(['request_payload' => $fields]);

        return view('payments.bridge', [
            'order' => $order,
            'fields' => $fields,
            'entryUrl' => $ipay88->entryUrl(),
        ]);
    }

    /**
     * ResponseURL (§D2) — browser redirect, UX ONLY. Never fulfils.
     */
    public function response(Request $request, Ipay88Service $ipay88)
    {
        $payload = $request->post();
        $payment = Payment::where('ref_no', $payload['RefNo'] ?? '')->latest('id')->first();

        if ($payment !== null && ! $ipay88->verifyResponseSignature($payload)) {
            Log::warning('iPay88 ResponseURL signature mismatch (UX path).', ['ref_no' => $payload['RefNo'] ?? null]);
        }

        if ($payment === null) {
            return redirect()->route('home');
        }

        return redirect()->route('payments.ipay88.processing', $payment->order);
    }

    /**
     * "Payment processing…" page that polls order state (§D2).
     */
    public function processing(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return view('payments.processing', ['order' => $order]);
    }

    /**
     * BackendURL (§D3) — server-to-server, the ONLY fulfilment trigger
     * (after requery). Must answer plain RECEIVEOK. Idempotent.
     */
    public function backend(Request $request, Ipay88Service $ipay88)
    {
        $payload = $request->post();
        $refNo = (string) ($payload['RefNo'] ?? '');

        $payment = Payment::where('ref_no', $refNo)->latest('id')->first();

        if ($payment === null) {
            Log::warning('iPay88 backend callback for unknown RefNo.', ['ref_no' => $refNo]);

            return response('RECEIVEOK');
        }

        if (! $ipay88->verifyResponseSignature($payload)) {
            // Flag + alert; do nothing else (docs/06 §D3, ops alert in docs/10).
            $payment->update(['signature_valid' => false]);
            Log::error('iPay88 backend signature MISMATCH — manual review required.', [
                'payment_id' => $payment->id,
                'ref_no' => $refNo,
            ]);

            return response('RECEIVEOK');
        }

        // Idempotency: same TransId already recorded as success → ack and stop.
        if ($payment->status === GatewayPaymentStatus::Success) {
            return response('RECEIVEOK');
        }

        $payment->update([
            'signature_valid' => true,
            'response_payload' => $payload,
        ]);

        if (($payload['Status'] ?? null) === '1') {
            ConfirmIpay88PaymentJob::dispatch($payment, $payload);
        } else {
            $payment->update([
                'status' => GatewayPaymentStatus::Failed,
                'requery_result' => $payload['ErrDesc'] ?? 'Failed',
            ]);
        }

        return response('RECEIVEOK');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\GatewayPaymentStatus;
use App\Jobs\ConfirmIpay88PaymentJob;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Ipay88Service;
use App\Settings\Ipay88Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

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

        return $this->entry($order, $ipay88, [
            'success' => route('payments.ipay88.mock', ['order' => $order->order_no, 'result' => 'success']),
            'fail' => route('payments.ipay88.mock', ['order' => $order->order_no, 'result' => 'fail']),
            'cancel' => route('checkout'),
        ]);
    }

    /**
     * Mobile hand-off (mobile plan §4): the SAME entry page as pay(), reached
     * from the app through a 15-minute signed URL — the signature is the auth,
     * there is no session. ResponseURL points at the app's return page; the
     * BackendURL is unchanged, so fulfilment still only ever comes from the
     * server-to-server callback + requery (hard rule 4).
     */
    public function appPay(Order $order, Ipay88Service $ipay88)
    {
        if (! $order->isAwaitingPayment()) {
            return view('payments.app-return', ['order' => $order, 'payable' => false]);
        }

        // 24h: long enough for any bank-redirect latency, short enough that a leaked
        // link dies with the order's own payment window.
        $returnUrl = URL::temporarySignedRoute('app.pay.return', now()->addDay(), ['order' => $order->order_no]);

        return $this->entry($order, $ipay88, [
            'success' => URL::temporarySignedRoute('app.pay.mock', now()->addMinutes(15), ['order' => $order->order_no, 'result' => 'success']),
            'fail' => URL::temporarySignedRoute('app.pay.mock', now()->addMinutes(15), ['order' => $order->order_no, 'result' => 'fail']),
            'cancel' => $returnUrl,
        ], $returnUrl);
    }

    /**
     * The one code path behind both entry pages: find or open the pending
     * payment attempt, then the simulator (mock mode) or the gateway bridge.
     *
     * @param  array{success: string, fail: string, cancel: string}  $mockActions  where the simulator's buttons go
     */
    private function entry(Order $order, Ipay88Service $ipay88, array $mockActions, ?string $responseUrl = null)
    {
        $payment = $this->pendingPayment($order);

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

        // No real gateway configured → built-in simulator (lets a preview finish
        // online-payment checkouts). Production sets a merchant code → real bridge.
        if ($ipay88->isMock()) {
            return view('payments.mock', ['order' => $order, 'payment' => $payment, 'actions' => $mockActions]);
        }

        $fields = $ipay88->entryFields($order, $payment, $responseUrl);

        $payment->update(['request_payload' => $fields]);

        return view('payments.bridge', [
            'order' => $order,
            'fields' => $fields,
            'entryUrl' => $ipay88->entryUrl(),
        ]);
    }

    private function pendingPayment(Order $order): ?Payment
    {
        return $order->payments()
            ->where('status', GatewayPaymentStatus::Pending)
            ->latest('id')
            ->first();
    }

    /**
     * Payment SIMULATOR confirm — ONLY when no real gateway is configured
     * (Ipay88Service::isMock). Mirrors a successful/failed callback so a preview
     * can complete checkout. Never reachable in production with real creds.
     */
    public function mockConfirm(Request $request, Order $order, string $result, Ipay88Service $ipay88)
    {
        abort_unless($ipay88->isMock(), 404);
        abort_unless($order->user_id === $request->user()->id, 403);

        $this->applyMock($order, $result);

        if ($result === 'success') {
            return redirect()->route('checkout.success', ['order' => $order->order_no]);
        }

        return redirect()->route('checkout')->with('toast', [
            'type' => 'error',
            'message' => __('Payment was not completed. Please try again.'),
        ]);
    }

    /** The app simulator: same outcome as mockConfirm(), then the app's return page. */
    public function appMock(Order $order, string $result, Ipay88Service $ipay88)
    {
        abort_unless($ipay88->isMock(), 404);

        $this->applyMock($order, $result);

        return redirect()->to(URL::temporarySignedRoute('app.pay.return', now()->addDay(), ['order' => $order->order_no]));
    }

    private function applyMock(Order $order, string $result): void
    {
        abort_unless($order->isAwaitingPayment(), 404);

        $payment = $this->pendingPayment($order);

        abort_if($payment === null, 404);

        if ($result === 'success') {
            // dispatchSync: confirm inline regardless of the queue driver.
            ConfirmIpay88PaymentJob::dispatchSync($payment, [
                'PaymentId' => 'MOCK',
                'TransId' => 'MOCK-'.$payment->ref_no,
                'AuthCode' => 'MOCK',
                'Status' => '1',
            ]);

            return;
        }

        $payment->update([
            'status' => GatewayPaymentStatus::Failed,
            'requery_result' => 'MOCK-FAILED',
        ]);
    }

    /**
     * The app's ResponseURL (GET from the simulator, POST from iPay88): a
     * plain "back to the app" page. UX only — it writes nothing, the app asks
     * the API for the payment status, which only the BackendURL path changes.
     */
    public function appReturn(Order $order)
    {
        return view('payments.app-return', ['order' => $order, 'payable' => true]);
    }

    /**
     * ResponseURL (§D2) — browser redirect, UX ONLY. Never fulfils.
     */
    public function response(Request $request, Ipay88Service $ipay88, Ipay88Settings $settings)
    {
        // AL-C13: with a blank merchant_key the signature check below is
        // fully reproducible by an unauthenticated caller (it's the only
        // secret in the hash). Inert this surface exactly when that check is
        // meaningless, rather than leave it open to a forged signature.
        // Doesn't touch the mock/simulator path — that's mockConfirm(),
        // gated separately by Ipay88Service::isMock(), which never posts here.
        abort_if(blank($settings->merchant_key), 404);

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
    public function backend(Request $request, Ipay88Service $ipay88, Ipay88Settings $settings)
    {
        // AL-C13: see response() above — same blank-merchant_key gate.
        abort_if(blank($settings->merchant_key), 404);

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

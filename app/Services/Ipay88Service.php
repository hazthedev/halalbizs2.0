<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGateway;
use App\Settings\Ipay88Settings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * iPay88 MY (hosted page) integration per technical doc family v1.6.x.
 * NOTE (docs/06 §D): verify field names, SignatureType, and PaymentId
 * channel codes against the latest PDF during merchant onboarding —
 * record the doc version here when confirmed.
 *
 * Signatures use the amount with separators stripped — which is exactly
 * our integer sen as a string (RM12.50 → "1250").
 */
class Ipay88Service implements PaymentGateway
{
    public function __construct(private Ipay88Settings $settings) {}

    public function name(): string
    {
        return 'ipay88';
    }

    /** A launch rail — always enabled (sandbox/prod toggled in Ipay88Settings). */
    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * No merchant code configured → run the built-in payment SIMULATOR instead
     * of hitting the real gateway, so a preview can complete online-payment
     * checkouts end-to-end. Production MUST set a merchant code (false then).
     */
    public function isMock(): bool
    {
        return blank($this->settings->merchant_code);
    }

    public function entryUrl(): string
    {
        return $this->settings->sandbox
            ? 'https://sandbox.ipay88.com.my/epayment/entry.asp'
            : 'https://payment.ipay88.com.my/ePayment/entry.asp';
    }

    public function requeryUrl(): string
    {
        return $this->settings->sandbox
            ? 'https://sandbox.ipay88.com.my/epayment/enquiry.asp'
            : 'https://payment.ipay88.com.my/ePayment/enquiry.asp';
    }

    /** sha256(MerchantKey . MerchantCode . RefNo . AmountNoSeparators . Currency) */
    public function requestSignature(string $refNo, int $amountSen, string $currency = 'MYR'): string
    {
        return hash('sha256', $this->settings->merchant_key.$this->settings->merchant_code.$refNo.$amountSen.$currency);
    }

    /** sha256(MerchantKey . MerchantCode . PaymentId . RefNo . AmountNoSeparators . Currency . Status) */
    public function responseSignature(string $paymentId, string $refNo, int $amountSen, string $currency, string $status): string
    {
        return hash('sha256', $this->settings->merchant_key.$this->settings->merchant_code.$paymentId.$refNo.$amountSen.$currency.$status);
    }

    public function verifyResponseSignature(array $payload): bool
    {
        $amountSen = self::amountToSen($payload['Amount'] ?? '0');

        $expected = $this->responseSignature(
            (string) ($payload['PaymentId'] ?? ''),
            (string) ($payload['RefNo'] ?? ''),
            $amountSen,
            (string) ($payload['Currency'] ?? 'MYR'),
            (string) ($payload['Status'] ?? ''),
        );

        return hash_equals($expected, (string) ($payload['Signature'] ?? ''));
    }

    /**
     * POST fields for the auto-submitting bridge form (docs/06 §D1).
     */
    public function entryFields(Order $order, Payment $payment): array
    {
        $user = $order->user;

        return [
            'MerchantCode' => $this->settings->merchant_code,
            'PaymentId' => '', // buyer picks the channel on the hosted page
            'RefNo' => $payment->ref_no,
            'Amount' => self::formatAmount($payment->amount_sen),
            'Currency' => 'MYR',
            'ProdDesc' => __('HalalBizs order :no', ['no' => $order->order_no]),
            'UserName' => $user->name,
            'UserEmail' => $user->email,
            'UserContact' => $user->phone ?? ($order->shipping_address['phone'] ?? ''),
            'Remark' => $order->order_no,
            'Lang' => 'UTF-8',
            'SignatureType' => 'SHA256',
            'Signature' => $this->requestSignature($payment->ref_no, $payment->amount_sen),
            'ResponseURL' => route('payments.ipay88.response'),
            'BackendURL' => route('payments.ipay88.backend'),
        ];
    }

    /**
     * Requery — the final source of truth before marking paid (§D4).
     * Body "00" = success.
     */
    public function requery(string $refNo, int $amountSen): string
    {
        $response = Http::asForm()->post($this->requeryUrl(), [
            'MerchantCode' => $this->settings->merchant_code,
            'RefNo' => $refNo,
            'Amount' => self::formatAmount($amountSen),
        ]);

        return trim($response->body());
    }

    /**
     * Best-effort automated refund. iPay88 hosted-page refunds are normally
     * issued in the merchant portal; an API refund endpoint exists only on some
     * merchant contracts. When one is configured we call it; otherwise we
     * return false and the recorded portal reference is the source of truth
     * (RefundService keeps the manual record either way). Never throws.
     */
    public function refund(Payment $payment, int $amountSen, ?string $reference = null): bool
    {
        $refundUrl = config('services.ipay88.refund_url');

        if (empty($refundUrl)) {
            Log::info('iPay88 refund requested with no API endpoint — recorded for manual portal refund.', [
                'ref_no' => $payment->ref_no,
                'amount_sen' => $amountSen,
                'reference' => $reference,
            ]);

            return false;
        }

        try {
            $response = Http::asForm()->post($refundUrl, [
                'MerchantCode' => $this->settings->merchant_code,
                'RefNo' => $payment->ref_no,
                'Amount' => self::formatAmount($amountSen),
                'Signature' => $this->requestSignature($payment->ref_no, $amountSen),
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('iPay88 refund API call failed.', ['ref_no' => $payment->ref_no, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** 125000 sen → "1250.00" (2dp, no thousand separators). */
    public static function formatAmount(int $sen): string
    {
        return sprintf('%d.%02d', intdiv($sen, 100), $sen % 100);
    }

    /** "1250.00" / "1,250.00" → 125000 sen. Integer math only. */
    public static function amountToSen(string $amount): int
    {
        $clean = str_replace(',', '', trim($amount));
        [$units, $minor] = array_pad(explode('.', $clean, 2), 2, '0');

        $units = (int) $units;
        $minor = (int) str_pad(substr($minor, 0, 2), 2, '0');

        return $units * 100 + $minor;
    }
}

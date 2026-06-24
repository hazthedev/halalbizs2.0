<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stripe driver for international cards/wallets/3DS (M1.9). Inert until a secret
 * key is configured, and FLAGGED for explicit go-live approval (Hard Rule 10) —
 * the platform's launch rails stay COD + iPay88. Uses the Stripe REST API
 * directly (no SDK package). Amounts are integer minor units (= our sen for MYR).
 */
class StripeGateway implements PaymentGateway
{
    private const BASE = 'https://api.stripe.com/v1';

    public function name(): string
    {
        return 'stripe';
    }

    public function isEnabled(): bool
    {
        return ! empty(config('services.stripe.secret'));
    }

    /** Create a PaymentIntent (returns the intent array, or null when disabled). */
    public function createIntent(int $amountSen, string $currency = 'myr', array $metadata = []): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        try {
            $response = Http::withToken(config('services.stripe.secret'))
                ->asForm()
                ->post(self::BASE.'/payment_intents', [
                    'amount' => $amountSen,
                    'currency' => strtolower($currency),
                    'automatic_payment_methods' => ['enabled' => 'true'],
                    'metadata' => $metadata,
                ]);

            return $response->successful() ? $response->json() : null;
        } catch (Throwable $e) {
            Log::error('Stripe createIntent failed.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function refund(Payment $payment, int $amountSen, ?string $reference = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $response = Http::withToken(config('services.stripe.secret'))
                ->asForm()
                ->post(self::BASE.'/refunds', array_filter([
                    'payment_intent' => $payment->ipay88_trans_id ?: $payment->ref_no,
                    'amount' => $amountSen,
                    'reason' => 'requested_by_customer',
                ]));

            return $response->successful();
        } catch (Throwable $e) {
            Log::error('Stripe refund failed.', ['ref_no' => $payment->ref_no, 'error' => $e->getMessage()]);

            return false;
        }
    }
}

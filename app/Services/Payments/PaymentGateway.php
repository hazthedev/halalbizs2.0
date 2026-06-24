<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * A settlement gateway (docs/ROADMAP.md M1.9). iPay88 aggregates the Malaysian
 * rails (FPX, wallets, cards, BNPL); Stripe covers international cards/wallets.
 * The driver-neutral contract lets the platform add gateways without touching
 * checkout. Money is always integer sen.
 */
interface PaymentGateway
{
    public function name(): string;

    public function isEnabled(): bool;

    /** Best-effort refund; false → caller falls back to manual / store credit. */
    public function refund(Payment $payment, int $amountSen, ?string $reference = null): bool;
}

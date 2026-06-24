<?php

namespace App\Services\Payments;

use App\Services\Ipay88Service;

/** Resolves payment gateways by name + lists the enabled ones (M1.9). */
class PaymentGatewayManager
{
    /** @return array<string, PaymentGateway> */
    public function all(): array
    {
        return [
            'ipay88' => app(Ipay88Service::class),
            'stripe' => app(StripeGateway::class),
        ];
    }

    public function driver(?string $name): ?PaymentGateway
    {
        return $name === null ? null : ($this->all()[$name] ?? null);
    }

    /** @return array<string, PaymentGateway> only the configured/enabled gateways */
    public function available(): array
    {
        return array_filter($this->all(), fn (PaymentGateway $gateway) => $gateway->isEnabled());
    }
}

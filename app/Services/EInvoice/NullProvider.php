<?php

namespace App\Services\EInvoice;

/**
 * Default provider: records the document locally without filing anywhere.
 * Used when no e-invoice regime is configured (local/dev, or markets without
 * a mandate). Documents stay `pending` — ready to be re-issued by a real
 * provider once credentials are supplied. Never makes network calls.
 */
class NullProvider implements EInvoiceProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function submit(array $document): EInvoiceResult
    {
        return EInvoiceResult::pending('No e-invoice provider configured — recorded locally.');
    }

    public function cancel(string $uin, string $reason): bool
    {
        return true;
    }
}

<?php

namespace App\Services\EInvoice;

/**
 * A pluggable e-invoicing provider. The platform builds a provider-neutral
 * document array (EInvoiceDocumentBuilder) and hands it here to be filed.
 * LHDN MyInvois is the first implementation; other jurisdictions register
 * their own driver and the rest of the system is unchanged.
 */
interface EInvoiceProvider
{
    public function name(): string;

    /** Submit a built document. Implementations must be idempotent-safe to retry. */
    public function submit(array $document): EInvoiceResult;

    /** Cancel a validated document within the provider's allowed window. */
    public function cancel(string $uin, string $reason): bool;
}
